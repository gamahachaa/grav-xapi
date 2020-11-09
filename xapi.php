<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Page\Page;
use Grav\Common\User\User;
use TinCan\Agent;
use TinCan\Activity;
use TinCan\ActivityDefinition;
use TinCan\Extensions;
use TinCan\RemoteLRS;
use TinCan\Verb;
use TinCan\Statement;
//use Grav\Plugin\XapiPlugin\Verbs;

/**
 * Class XapiPlugin
 * @package Grav\Plugin
 */
class XapiPlugin extends Plugin {

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    protected $user;
    protected $page;
    protected $pname;
    //protected $cache; // @todo
    //LRS array
    protected $lrss;
    protected $activityTypes;
    protected $verbs;
    
    // endpoint
    protected $endpoint;
    protected $username;
    protected $passwd;
    protected $lrs;
    // statement
    protected $verb;
    protected $actor; //TinCan\Agent
    protected $activity; //TinCan\Activity
    // search queries
    protected $search_key;
    
     

    public static function getSubscribedEvents() {

        return [
            'onPageInitialized' => ['onPageInitialized', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onFormProcessed' => ['onFormProcessed', 0],
            'onPluginsInitialized' => [
                    ['autoload', 100000],
                    ['onPluginsInitialized', 0]
            ]
        ];
    }

    /**
     * Register TINCAN classes
     * [onPluginsInitialized:100000] Composer autoload.
     * 
     * @return ClassLoader
     */
    public function autoload() {
        return require __DIR__ . '/vendor/autoload.php';
    }

    //********************************************* EVENTS HANDLERS **************************************************************/
    //********************************************* EVENTS HANDLERS **************************************************************/
    //********************************************* EVENTS HANDLERS **************************************************************/
    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized() {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }
        // Check to ensure login plugin is enabled.
        if (!$this->grav['config']->get('plugins.login.enabled')) {
            throw new \RuntimeException('The Login plugin needs to be installed and enabled');
        }
        //$this->grav['debugger']->addMessage("XAPI onPluginsInitialized");
        $this->search_key = $this->config->get('plugins.' . $this->pname . '.search_queries.q');
         $this->lrss = [];
         $this->activityTypes = [];
         $this->verbs = [];
        // todo caching 
        //$this->cache = $this->grav['cache'];
        $this->pname = 'xapi';
        
        $this->user = $this->grav['user'];
        $this->grav['debugger']->addMessage($this->grav['user']);
        $this->actor = $this->prepareAgent($this->user);
        // SET LRS credentials based on user's group profile
    }
     //********************************************* JS **************************************************************/

    public function onTwigSiteVariables(Event $event) {
        if (!$this->config->get('plugins.' . $this->pname . '.js.active'))
            return;
        $this->grav['assets']->addJs('plugin://' . $this->pname . '/js/tincan-min.js');
      
    }
    // @todo add more JS functionalities
    
 //********************************************* PHP **************************************************************/

    /**
     *
     * @param Event $e
     */
    public function onPageInitialized(Event $e) {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }
         //$this->grav['debugger']->addMessage("XAPI onPageInitialized");
        if (!$this->user->authorize('site.login')) {
            return;
        }
        $this->page = $e['page'];
        // prepares LRSs
        $this->mapConfigNamedCollections($this->config->get('plugins.' . $this->pname . '.lrs'), $this->lrss );
       // prepare templates activities
        $this->mapConfigNamedCollections($this->config->get('plugins.' . $this->pname . '.template_activityType'), $this->activityTypes );
        // prepare temaplates verbs 
        $this->mapConfigNamedCollections($this->config->get('plugins.' . $this->pname . '.template_verb'), $this->verbs );
        
        
        
        if ($this->filter()) {
            if ($this->config->get('plugins.' . $this->pname . '.php.active')) {
                $remote = $this->prepareLRS($this->user);
                $this->trackFromServer($remote);
            }
        }
    }

    /**
     * catch plugin Form event
     * @param Event $event
     * @throws \RuntimeException
     */
    public function onFormProcessed(Event $event) {
        // Check to ensure Form plugin is enabled.
        if (!$this->grav['config']->get('plugins.form.enabled')) {
            throw new \RuntimeException('The Form plugin needs to be installed and enabled');
        }

        $action = $event['action'];

        switch ($action) {
            case 'xapi':
                $params = $event['params'];
                $form = $event['form'];
                $lrs = $this->prepareLRS($this->user);

                $statement = $this->prepareStatement($params['verb'] ?? '', $this->prepareExtentions($params['extensions'], $form));
                $response = $lrs->saveStatement($statement);
                
                //uncomment for debugging
                if ($response->success) {
                    //$this->grav['debugger']->addMessage("Statement sent successfully!\n");
                } else {
                    //$this->grav['debugger']->addMessage("Error statement not sent: " . $response->content . "\n");
                }
                break;
        }
    }

    /*********************************************************************************************************************
    *******************************************PRIVATES*******************************************************************
    *********************************************************************************************************************/
       
    private function mapConfigNamedCollections($configVar, &$thisVar)
    {
        
        foreach( $configVar as $var )
        {
            $thisVar[$var['naming']] = [];
            foreach( $var as $k=> $v)
            {
                if($k == 'naming') continue;
                else{
                    $thisVar[$var['naming']][$k] = $v;
                }
            }
        }
    }
    private function prepareQueries($tab)
    {
        $q=[];
        $trackAsExtension = $this->config->get('plugins.' . $this->pname . '.track_queries_as_extension');
            
        $tmp = [];
        foreach ($tab as $v)
        {
            $tmp = explode ("=", $v);
            if(strpos($tmp[0], $this->config->get('plugins.' . $this->pname . '.search_queries.key')))
            {
                ////https://w3id.org/xapi/dod-isd/verbs/found
                $stmt = $this->prepareStatement('https://w3id.org/xapi/dod-isd/verbs/found', [$tmp[0]=>$tmp[1]]);
                // SEND STATEMENT
                $r = $lrs->saveStatement($stmt);
            }
            else if ($trackAsExtension)
            {
                $q[$tmp[0]]=$tmp[1];
            }
        }
        return $q;
    }
    private function trackFromServer(RemoteLRS &$lrs = null) {
        //track_as_extension: true
        $uri_query = $this->grav['uri']->query();
        $url_query_tab = explode("&", $uri_query);
        
        $queries = $this->prepaprepareQueries($url_query_tab);
        
//        if(sizeof($url_query_tab)>0)
//        {
//            $trackAsExtension = $this->config->get('plugins.' . $this->pname . '.track_queries_as_extension');
//            
//            $tmp;
//            foreach ($url_query_tab as $v)
//            {
//                $tmp = explode($v, "=");
//                if(strpos($tmp[0], $this->config->get('plugins.' . $this->pname . '.search_queries.key')))
//                {
//                    ////https://w3id.org/xapi/dod-isd/verbs/found
//                    $stmt = $this->prepareStatement('https://w3id.org/xapi/dod-isd/verbs/found', [$tmp[0]=>$tmp[1]]);
//                    // SEND STATEMENT
//                    $r = $lrs->saveStatement($stmt);
//                }
//                else if ($trackAsExtension)
//                {
//                    $queries[$tmp[0]]=$tmp[1];
//                }
//            }
//        }
 
        if(sizeof($queries)>0)
        {
            $statement = $this->prepareStatement('', $queries);
        }
        else{
            $statement = $this->prepareStatement();
        }
        
        // SEND STATEMENT
        $response = $lrs->saveStatement($statement);
        
        if ($response) {
            //uncomment for debugging
             /***
            $this->grav['debugger']->getCaller();
            $this->grav['debugger']->addMessage('trackFromServer success');
            $this->grav['debugger']->addMessage($statement);
             **/
        } else {
            //uncomment for debugging
            /***
            $this->grav['debugger']->addMessage('trackFromServer failed');
            $this->grav['debugger']->addMessage($statement);
            * */
        }

    }

    /**
     * Get the LRS base on the config group mapping to list of LRS
     * Sets the connection to the first found matching LRS
     * @param User $u
     * @return \Tincan\RemoteLRS
     */
    private function prepareLRS(User $u) {
        $lrs_config = $this->getFirstLRSConfigFromUser($u);
        return new RemoteLRS($lrs_config['endpoint'], $lrs_config['version'], $lrs_config['username'], $lrs_config['password']);
    }

    /**
     * Get verb based on page template mapped to config list of templates/verbs
     * @param type $template
     * @return \TinCan\Verb
     * @todo statics for common used verbs with multilang desc
     */
    protected function prepareVerb($verbID = '') {
        $this->grav['debugger']->addMessage('grav xapi prepareVerb');
        if ($verbID == '') 
        {
            $id = $this->verbs[$this->page->template()]['verbIRI']??$this->verbs['default']['verbIRI'];
        } else {
            $id = $verbID;
        }
        
        $id_array = explode('/',$id);
        // make sure verb uri doesnt finish with 
        $endisplay = end($id_array)==""?prev($id_array):end($id_array);
        //echo $page = end($link_array);
        return new \TinCan\Verb([
            'id' => $id,
            'display' => [
                'en-US' => $endisplay
            ]
        ]);
    }

    /**
     * 
     * @param User $gravUser
     * @return Agent
     * @todo add functionality to prepare agent as group (for https://github.com/adlnet/xAPI-Spec/blob/master/xAPI-Data.md#2422-when-the-actor-objecttype-is-group)
     */
    private function prepareAgent(User $gravUser) {
        return new Agent([
            'mbox' => 'mailto:' . strtolower($gravUser->email),
            'name' => strtolower($gravUser->username)
        ]);
    }

    /**
     * 
     * @param type $page
     * @return Activity
     */
    private function prepareActivity() 
    {
        $object = new \TinCan\Activity();
        
//        $query = $this->grav['uri']->query() == '' ? '' : "?" . $this->grav['uri']->query();
//        $activity_id = "https://" . $this->grav['uri']->host() . $this->grav['uri']->path() . $query;
        $activity_id = "https://" . $this->grav['uri']->host() . $this->grav['uri']->path();
        $object->setId($activity_id);
        $object->setDefinition($this->prepareActivitytDefintionFromPage($this->page));
        return $object;
    }

    /**
     * 
     * @param Page $page
     * @return \TinCan\Context
     */
    private function prepareContext() {
        $context = new \TinCan\Context();
        $context->setPlatform($this->grav['config']->get('site.title'));
        $context->setLanguage($this->page->language());
        return $context;
    }

    /**
     * Prepare statement to LRS
     * @param \Grav\Plugin\Grav\Common\Page\Page $page
     * @return type
     */
    protected function prepareStatement($verbID = '', Extensions $extensions = null) {
        $object = $this->prepareActivity();
        if (!is_null($extensions)) {
            $object->getDefinition()->setExtensions($extensions);
        }
        $statement = New Statement([
            'actor' => $this->actor,
            'verb' => $this->prepareVerb($verbID),
            'object' => $object,
            'context' => $this->prepareContext()
        ]);
        return $statement;
    }

    /**
     * Tin can extensions for non result based forms
     * @param type $params
     * @param type $form
     * @return Extensions
     */
    private function prepareExtentions($params = [], $form) {
        /** @var Twig $twig */
        $twig = $this->grav['twig'];
        $vars = [
            'form' => $form
        ];
        $exts = [];
        $val;
        $key;
        foreach ($params as $k => $v) {

            $val = $form['data'][$v];
            $key = $k;
            // compute extension's value
            if (strrpos($v, '{{') >= 0 && strrpos($v, '}}') > 0) {
                // Process with Twig only if twig markup found
                $val = $twig->processString($v, $vars);
            } else if (is_null($val)) {
                //get value from the form
                $val = $v;
            }
            // Compute extension id
            if (strrpos($k, "http") !== 0) {
                // create extension ID if not defined in the fonm
                $key = $this->grav['uri']->url . "#" . $k;
            }
            $exts[$key] = $val;
        }
        return new Extensions($exts);
    }

    /**
     * 
     * @return ActivityDefinition
     */
    private function prepareActivitytDefintionFromPage() {
        $desc = [];
        $language = $this->page->language() ?: 'en';

        $desc['name'] = [$language => $this->page->title()];
        // set the activity type
        $desc['type'] = $this->activityTypes[$this->page->template()]['activityIRI']??$this->activityTypes['default']['activityIRI'];
        if (isset($this->page->header()->metadata) && isset($this->page->header()->metadata['description'])) {
            $desc['description'] = [$language => $this->page->header()->metadata['description']];
        }
        /**
         * @todo make definition creation flexible before publishing.
         */
        return new ActivityDefinition($desc);
    }

   
    //********************************************* GENERIC **************************************************************/
    /**
     * 
     * @return boolean
     */
    private function filter() {
        // DO not track modulars (does not affect pages made of collections)
        if ($this->page->modular())
            return false;
       // $this->grav['debugger']->addMessage('grav xapi filter');
        
        // do not track routes and uri queries
        // Do not track a certain page based on its template

        if ($this->config->get('plugins.' . $this->pname . '.filter.template') && in_array($this->page->template(), $this->config->get('plugins.' . $this->pname . '.filter.template')))
            return false;
        //$this->grav['debugger']->addMessage('Template not filtererd : ' . $this->page->template());
        if ($this->config->get('plugins.' . $this->pname . '.filter.uri')) {
            $uri = $this->grav['uri'];

            /**
             * @todo add wild cards
             */
            // routes
            if ($this->config->get('plugins.' . $this->pname . '.filter.uri.routes')) {
                $filtered_routes = $this->config->get('plugins.' . $this->pname . '.filter.uri.routes');
                foreach ($filtered_routes as $v) {
                    if ($uri->route() === $v)
                        return false;
                }
            }
            //$this->grav['debugger']->addMessage('uri.routes not filtererd : '.$uri->route());
            // queries
            if ($this->config->get('plugins.' . $this->pname . '.filter.uri.query')) {
                $filtered_queries = $this->config->get('plugins.' . $this->pname . '.filter.uri.query');
                foreach ($filtered_queries as $v) {
                    if($v['value']=='' )
                    {
                        return false;
                    }
                    else if ($uri->query($v['key']) === $v['value'])
                        return false;
                    //$this->grav['debugger']->addMessage('uri.query not filtererd : '.$uri->query($v['key']));
                }
            }
        }
        
        // Do not track users
        if ($this->config->get('plugins.' . $this->pname . '.filter.users') && in_array($this->user->login, $this->config->get('plugins.' . $this->pname . '.filter.users'))) {
            return false;
        }
        //$this->grav['debugger']->addMessage('users not filtererd : ' . $this->user->login);
        // Do not track users if they belong to a certain group
        if ($this->config->get('plugins.' . $this->pname . '.filter.groups')) {
            if (isset($this->user->groups)) {
                foreach ($this->user->groups as $g) {
                    if (in_array($g, $this->config->get('plugins.' . $this->pname . '.filter.groups'))) {
                        return false;
                    }
                }
            }
        }
        // do not track pages with particular taxo
        $sysTaxo = $this->grav['config']->get('site.taxonomies');
        $pageTaxo = $this->page->taxonomy();
        foreach ($sysTaxo as $t) {
            $filterTaxo = $this->config->get('plugins.' . $this->pname . '.filter.taxonomies.' . $t);
            if (isset($filterTaxo) && isset($pageTaxo[$t])) {
                foreach ($filterTaxo as $ft) {
                    if (in_array($ft, $pageTaxo[$t])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Look in the user's group list if an LRS was defined for it and return firt found
     * @param User $u
     * @return Array
     */
    private function getFirstLRSConfigFromUser(User $u) {
        if (isset($u->groups)) {
            foreach ($u->groups as $g) {
                if (isset($this->lrss[$g])) {
                    return $this->lrss[$g];
                }
            }
        }
        return $this->lrss['default'];
    }


/////////////////////////////////////////////////////////// DUMMY  ///////////////////////////////////////////////////
    function getTestStatement() {
        return [
            'actor' => [
                'mbox' => 'test@test.com',
            ],
            'verb' => [
                'id' => 'http://adlnet.gov/expapi/verbs/experienced',
            ],
            'object' => [
                'id' => 'http://rusticisoftware.github.com/TinCanPHP',
            ],
        ];
    }
    function getTestLRS() {
        new RemoteLRS(
                'https://cloud.scorm.com/lrs/XXXXXXXXX', '1.0.1', 'username', 'pwd'
        );
    }

}
