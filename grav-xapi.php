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
use TinCan\Verbs;
use TinCan\Statement;
/**
 * Class XapiPlugin
 * @package Grav\Plugin
 */
class GravXapiPlugin extends Plugin {

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
    // endpoint
    protected $endpoint;
    protected $username;
    protected $passwd; 
    protected $lrs;
    // statement
    protected $verb;
    protected $actor; //TinCan\Agent
    protected $activity; //TinCan\Activity

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
    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized() {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        $this->cache = $this->grav['cache'];
        $this->pname = 'grav-xapi';
        $this->grav['debugger']->addMessage('onPluginsInitialized GravXapiPlugin');
        // Check to ensure login plugin is enabled.
        if (!$this->grav['config']->get('plugins.login.enabled')) {
            throw new \RuntimeException('The Login plugin needs to be installed and enabled');
        }
        $this->user = $this->grav['user'];
        $this->actor = $this->prepareAgent($this->user);
        // SET LRS credentials based on user's group profile
//        $this->prepareLRS($this->user);
    }

    /**
     * Set needed ressources to display and convert charts
     */
    public function onTwigSiteVariables(Event $event) {
        // Resources for the conversion
        if (!$this->config->get('plugins.'.$this->pname.'.js.active'))
            return;
//        $this->grav['debugger']->addMessage($this->pname . ' use JS');
        $this->grav['assets']->addJs('plugin://' . $this->pname . '/js/tincan-min.js');
        $this->grav['assets']->addInlineJs('$("#tribunehelpbtn").click(function(){alert("TRIBUNE")});', ['group' => 'bottom']);
        /*
         * window.onunload = (e) => {console.log(e);};
         */
        //$this->grav['assets']->addJs('plugin://grav-diagrams/js/main.min.js');
    }

    /**
     *
     * @param Event $e
     */
    public function onPageInitialized(Event $e) {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }
        //$this->grav['debugger']->addMessage('onPluginsInitialized');
        if (!$this->user->authorize('site.login')) {
            return;
        }
        $this->page = $e['page'];
//        $this->config = $this->mergeConfig($this->page);
//        $this->grav['debugger']->addMessage('CONFIG');
//        $this->grav['debugger']->addMessage($this->config);
        if ($this->filter()) {
            if ($this->config->get('plugins.'.$this->pname.'.php.active')) {
                $remote = $this->preparePhpLRS($this->user);
                $this->trackPhp($remote);
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
                // NORMAL
//                $this->grav['debugger']->addMessage(Verbs::$reviewed);
                $lrs = $this->preparePhpLRS($this->user);
                
                // TEST
//                $lrs = new RemoteLRS(
//                    'https://qast.test.salt.ch/data/xAPI',
//                    '1.0.1',
//                    '3b5d9d1f2fc76e9cdb663f311613d5663fc694d1',
//                    'fa4a6689c00153b393905e095f44510d34138195'
//                );
                /**
                 * TEST statement for debugging
                 */
//                $statement = $this->getTestStatement();
                /**
                 * normal statement
                 */
                $statement = $this->prepareStatement($params['verb'], $this->prepareExtentions($params['extensions'], $form));
                
                $response = $lrs->saveStatement($statement);
                if ($response->success) {
                    $this->grav['debugger']->addMessage("Statement sent successfully!\n");
                } else {
                    $this->grav['debugger']->addMessage("Error statement not sent: " . $response->content . "\n");
                }
                break;
            //do what you want
        }
    }
    //********************************************* PRIVATES **************************************************************/

    //********************************************* PHP **************************************************************/
    private function trackPhp(RemoteLRS &$lrs = null) {

//        $this->grav['debugger']->addMessage($this->pname . ' use PHP');

        $statement = $this->prepareStatement();
//        $this->grav['debugger']->addMessage($statement->verify());
        // SEND STATEMENT
        $response = $lrs->saveStatement($statement);
        if ($response) {

            $this->grav['debugger']->getCaller();
            $this->grav['debugger']->addMessage('success');
        } else {

            $this->grav['debugger']->addMessage('failed');
            $this->grav['debugger']->addMessage($statement);
        }
    }
    /**
     * Get the LRS base on the config group mapping to list of LRS
     * Sets the connection to the first found matching LRS
     * @param User $u
     * @return \Tincan\RemoteLRS
     */
    private function preparePhpLRS(User $u) {
        $lrs_config = $this->getLRConfigFromUSer($u);
//        $this->grav['debugger']->addMessage($lrs_config);
        
        $lrs = new RemoteLRS($lrs_config[0], $lrs_config[1], $lrs_config[2], $lrs_config[3]);
//       $this->grav['debugger']->addMessage($lrs);
       return $lrs;
    }
    /**
     * Get verb based on page template mapped to config list of templates/verbs
     * @param type $template
     * @return \TinCan\Verb
     * @todo statics for common used verbs with multilang desc
     */
    protected function prepareVerb($verbID= '') {
        if($verbID == '')
        {
            $id = $this->config->get('plugins.'.$this->pname.'.template_verb.default');
            if ($this->config->get('plugins.'.$this->pname.'.template_verb.' . $this->page->template())) {
                $id = $this->config->get('plugins.'.$this->pname.'.template_verb.' . $this->page->template());
            }
        }
        else{
            $id = $verbID;
        }
        return new \TinCan\Verb([
            'id' => $id
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
            'mbox' => 'mailto:' . $gravUser->email,
            'name' => $gravUser->login
        ]);
    }
    /**
     * 
     * @param type $page
     * @return Activity
     */
    private function prepareActivity() {
        $object = new \TinCan\Activity();
        $query = $this->grav['uri']->query() == '' ? '' : "?" . $this->grav['uri']->query();
        $activity_id = "https://" . $this->grav['uri']->host() . $this->grav['uri']->path() . $query;
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
     * Send statement to LRS
     * @param \Grav\Plugin\Grav\Common\Page\Page $page
     * @return type
     */
    protected function prepareStatement( $verbID = '', Extensions $extensions = null)
    {
        $object = $this->prepareActivity();
        if (!is_null($extensions)) {
            $object->getDefinition()->setExtensions($extensions);
        }
        
        //$object->setDefinition(  );
        // BUILD
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
        //$this->grav['debugger']->addMessage($form['data']);
        foreach ($params as $k => $v) {
//            $this->grav['debugger']->addMessage('################');
//            $this->grav['debugger']->addMessage($k);
//            $this->grav['debugger']->addMessage($v);
//            
//            
//            $this->grav['debugger']->addMessage(strrpos($v, '{{'));
//            $this->grav['debugger']->addMessage(strrpos($v, '}}'));
            $val = $form['data'][$v];
            if (strrpos($v, '{{')>=0 && strrpos($v, '}}')>0) {
                // Process with Twig only if twig markup found
                // non form values
//                $this->grav['debugger']->addMessage('PROCESS TWIG in grav-xapi');
                $val = $twig->processString($v, $vars);
            } else if(is_null($val)){
                //get value from the form
                $val = $v;
            }
            
             $key;
            if (strrpos($k, "http") === 0) {
                
                //$exts[$k] = $val;
                
                $key = $k;
            } else {
//                $this->grav['debugger']->addMessage('ADD form URL');
                // create extension ID if not defined in the fonm
                 //$exts[$this->grav['uri']->url . "#" . $k] = $val;
                 $key = $this->grav['uri']->url . "#" . $k;
                
            }
//            $this->grav['debugger']->addMessage('---------------');
//             $this->grav['debugger']->addMessage($val);
//              $this->grav['debugger']->addMessage($key);
            $exts[$key] = $val;
        }
        return new Extensions($exts);
    }
    /**
     * 
     * 
     * @return ActivityDefinition
     */ 
    private function prepareActivitytDefintionFromPage() {
        $desc = [];
        $language = $this->page->language()?:'en';
        
        $desc['name'] = [$language => $this->page->title()];
        $desc['type'] = $this->getConfigByTemplate('template_activityType', $this->page->template());
        if(isset($this->page->header()->metadata) && isset($this->page->header()->metadata['description']))
        {
            $desc['description'] = [$language => $this->page->header()->metadata['description']];
        }
        /**
         * @todo make definition creation flexible before publishing.
         */
        return new ActivityDefinition( $desc );
    }
    //********************************************* JS **************************************************************/
    //********************************************* GENERIC **************************************************************/
    /**
     * 
     * @return boolean
     */
    private function filter() {
        // do not track routes and uri queries
        //$this->grav['debugger']->addMessage($this->config);
        if ($this->config->get('plugins.'.$this->pname.'.filter.uri')) {
            $uri = $this->grav['uri'];
            /**
             * @todo add wild cards
             */
            // routes
            if ($this->config->get('plugins.'.$this->pname.'.filter.uri.routes')) {
                $filtered_routes = $this->config->get('plugins.'.$this->pname.'.filter.uri.routes');
                foreach ($filtered_routes as $v) {
                    if ($uri->route() === $v)
                        return false;
                }
            }
            // queries
            if ($this->config->get('plugins.'.$this->pname.'.filter.uri.query')) {
                $filtered_queries = $this->config->get('plugins.'.$this->pname.'.filter.uri.query');
                foreach ($filtered_queries as $v) {
                    if ($uri->query($v['key']) === $v['value'])
                        return false;
                }
            }
        }
        // DO not track modulars (does not affect pages made of collections)
        if ($this->page->modular())
            return false;

        // Do not track a certain page based on its tempoale
        if ($this->config->get('plugins.'.$this->pname.'.filter.template') && in_array($this->page->template(), $this->config->get('plugins.'.$this->pname.'.filter.template')))
            return false;
        // Do not track users
        if ($this->config->get('plugins.'.$this->pname.'.filter.users') && in_array($this->user->login, $this->config->get('plugins.'.$this->pname.'.filter.users'))) {
            return false;
        }
        // Do not track users if they belong to a certain group
        if ($this->config->get('plugins.'.$this->pname.'.filter.groups')) {
            if (isset($this->user->groups)) {
                foreach ($this->user->groups as $g) {
                    if (in_array($g, $this->config->get('plugins.'.$this->pname.'.filter.groups'))) {
                        return false;
                    }
                }
            }
        }
        // do not track pages with particular taxo
        $sysTaxo = $this->grav['config']->get('site.taxonomies');
        $pageTaxo = $this->page->taxonomy();
        foreach ($sysTaxo as $t) {
            $filterTaxo = $this->config->get('plugins.'.$this->pname.'.filter.taxonomies.' . $t);
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
     * 
     * @param User $u
     * @return Array
     */
    private function getLRConfigFromUSer(User $u)
    {
        $lrs = 'default';
        if (isset($u->groups)) {
            foreach ($u->groups as $g) {
                if ($this->config->get('plugins.'.$this->pname.'.lrs.' . $g)) {
                    $lrs = $g;
                    break;
                }
            }
        }
        return $this->getLrsConfig($lrs);
    }
    /**
     * Get the LRS connection details based on the chosen LRS
     * @param type $lrs
     * @return type
     */
    private function getLrsConfig($lrs) {
//        $this->grav['debugger']->addMessage($lrs);
//        $this->grav['debugger']->addMessage($this->config);
//        $this->grav['debugger']->addMessage($this->config->get('plugins.'.$this->pname.'.lrs'));
//        $this->grav['debugger']->addMessage($this->config->get('plugins.'.$this->pname.'.lrs.' . $lrs . '.endpoint'));
//        $this->grav['debugger']->addMessage($this->config->get('plugins.'.$this->pname.'.lrs.' . $lrs . '.version'));
//        $this->grav['debugger']->addMessage($this->config->get('plugins.'.$this->pname.'.lrs.' . $lrs . '.username'));
//        $this->grav['debugger']->addMessage($this->config->get('plugins.'.$this->pname.'.lrs.' . $lrs . '.password'));

        return [
            $this->config->get('plugins.'.$this->pname.'.lrs.' . $lrs . '.endpoint'),
            $this->config->get('plugins.'.$this->pname.'.lrs.' . $lrs . '.version'), 
            $this->config->get('plugins.'.$this->pname.'.lrs.' . $lrs . '.username'), 
            $this->config->get('plugins.'.$this->pname.'.lrs.' . $lrs . '.password')
            ];
    }

    

    function getConfigByTemplate($config, $template) {
        $tmp = $this->config->get($config . '.' . $template);
        return is_null($tmp) ? $this->config->get($config . '.default') : $tmp;
    }
/////////////////////////////////////////////////////////// DUMMY  ///////////////////////////////////////////////////
    function getTestStatement()
    {
        return [
        'actor' => [
            'mbox' => 'test@salt.ch',
        ],
        'verb' => [
            'id' => 'http://adlnet.gov/expapi/verbs/experienced',
        ],
        'object' => [
            'id' => 'http://rusticisoftware.github.com/TinCanPHP',
        ],
    ];
    }
    

}
