<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Page\Page;
use Grav\Common\User\User;

/**
 * Class XapiPlugin
 * @package Grav\Plugin
 */
class GravXapiPlugin extends Plugin
{
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
    protected $grav;
    protected $user;
    protected $time;
    protected $lrs;
    /* @var $page Grav\Common\Page\Page */
    protected  $page;
    protected $pname;
    public static function getSubscribedEvents()
    {
        return [
//            'onPageContentProcessed' => ['onPageContentProcessed', 0],
            'onPageInitialized' => ['onPageInitialized', 0],
            'onShutdown' => ['onShutdown', 0],
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        $this->pname = 'grav-xapi';
        // Check to ensure login plugin is enabled.
        if (!$this->grav['config']->get('plugins.login.enabled')) {
            throw new \RuntimeException('The Login plugin needs to be installed and enabled');
        }
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }
    }
    /**
     * [onPluginsInitialized:100000] Composer autoload.
     *
     * @return ClassLoader
     */
    public function autoload()
    {
        return require __DIR__ . '/vendor/autoload.php';
    }
    /**
     * Do some work for this event, full details of events can be found
     * on the learn site: http://learn.getgrav.org/plugins/event-hooks
     *
     * @param Event $e
     */
    public function onPageInitialized(Event $e)
    {
        // Get a variable from the plugin configuration
        $grav = $this->grav;
        $this->user = $grav['user'];
        if(!$this->user->authorize('site.login'))
        {
            return;
        }
        $this->page = $e['page'];
        $this->prepareLRS($this->user);
        
        // Get the current raw content
        $this->grav['debugger']->addMessage('onPageInitialized');
        if($this->filter() )
        {
            $this->grav['debugger']->addMessage('befor DO STATEMENT');
            $response = $this->doStatement($e['page']);
            $this->grav['debugger']->addMessage('after DO STATEMENT');
            if($response)
            {
                $this->grav['debugger']->addMessage('success');
            }
            else{
                $this->grav['debugger']->addMessage('failed');
            }
            $this->time = time();
            setcookie("start", $this->time, $this->time + 3600, "/");
        }
        
    }
    function filter(){
        
//        $this->grav['debugger']->addMessage('filter');
        // DO not track modular
        if($this->page->modular()) 
            return false;
        // Do not track if user is listed in the plugin (typically for admins)
        if(in_array($this->user->login, $this->grav['config']->get('plugins.'.$this->pname.'.filter.users' ))){
            return false;
        }
        // Do not track a certain page template
        if( in_array($this->page->template(), $this->grav['config']->get('plugins.'.$this->pname.'.filter.template' ))) 
                return false;
        // Do not track if user's groups are in filter's groups
        foreach ( $this->user->groups as $g)
        {
            if(in_array($g, $this->grav['config']->get('plugins.'.$this->pname.'.filter.groups' ))){
                return false;
            }
        }
        // do not track pages with particular taxo
        $sysTaxo =  $this->grav['config']->get('site.taxonomies' );
        $pageTaxo = $this->page->taxonomy();
        foreach ( $sysTaxo as $t ) 
        {
           $filterTaxo = $this->grav['config']->get('plugins.'.$this->pname.'.filter.taxonomies.'.$t );
           if(isset($filterTaxo) && isset($pageTaxo[$t]))
           {
               foreach($filterTaxo as $ft)
               {
                   $this->grav['debugger']->addMessage($pageTaxo[$t]);
                   $this->grav['debugger']->addMessage($ft);
                   if(in_array($ft, $pageTaxo[$t]))
                   {
                       $this->grav['debugger']->addMessage( 'filtered ');
                       return false;
                   }
               }
           }
               
        }
         $this->grav['debugger']->addMessage('passed filter');
        return true;
    }
    /**
     * Get the LRS base on the config group mapping to list of LRS
     * Sets the connection to the first found matching LRS
     * @param User $u
     */
    protected function prepareLRS(User $u)
    {
        $config = 'default';
        if(isset($u->groups))
        {
            foreach ($u->groups as $g)
            {
                if($this->grav['config']->get('plugins.'.$this->pname.'.lrs.'.$g))
                {
                    $config = $g;
                    break;
                }
            }
        }
        
        $this->lrs = new \TinCan\RemoteLRS(
            $this->grav['config']->get('plugins.'.$this->pname.'.lrs.'.$config.'.endpoint'),
            '1.0.1',
            $this->grav['config']->get('plugins.'.$this->pname.'.lrs.'.$config.'.username'),
            $this->grav['config']->get('plugins.'.$this->pname.'.lrs.'.$config.'.password')
        );
        try{
            $about = $this->lrs->about();
            $this->grav['debugger']->addMessage($about);
        }
        catch(ErrorException $e)
        {
            dump($e);
        }
    }
    /**
     * Get verb based on page template mapped to config list of templates/verbs
     * @param type $template
     * @return \TinCan\Verb
     */
    protected function prepareVerb($template)
    {
//        $this->grav['debugger']->addMessage('prepareVerb');
//        $this->grav['debugger']->addMessage($template);
//        $this->grav['debugger']->addMessage($this->grav['config']->get('plugins.'.$this->pname.'.verb.'.$template));
        if($this->grav['config']->get('plugins.'.$this->pname.'.verb.'.$template))
        {
            return new \TinCan\Verb([
                    'id' => $this->grav['config']->get('plugins.'.$this->pname.'.verb.'.$template)
                ]);
        }
       return new \TinCan\Verb([
                    'id' => $this->grav['config']->get('plugins.'.$this->pname.'.verb.default')
                ]);
    }
    /**
     * Send statement to LRS
     * @param \Grav\Plugin\Grav\Common\Page\Page $page
     * @return type
     */
    protected function doStatement(Page $page)
    {
        $header = $page->header();
        // WHO
        $actor = new \TinCan\Agent([
                    'mbox' => 'mailto:'.$this->user->email,
                    'name' => $this->user->login
                ]);
        
        // DID
        $verb = $this->prepareVerb($page->template());
        // WHAT
        $object = new \TinCan\Activity();
        $object->setId($page->canonical(false));
        $object->setDefinition([
                        'name' => [
                            $page->language() => $page->title()
                        ],
                        'description' => [
                            $page->language() => isset($header->metadata) && isset($header->metadata['description'])?$header->metadata['description']:'',
                        ],
                        'type' => $page->template()=="listing"?'http://activitystrea.ms/schema/1.0/collection':'http://activitystrea.ms/schema/1.0/page'
                    ]);
        // HOW
        $context = new \TinCan\Context();
        $context->setPlatform($this->grav['config']->get('site.title'));
        $context->setLanguage($page->language());
        // PUSH
        $statement = New \TinCan\Statement([
                'actor' => $actor,
                'verb' => $verb,
                'object' => $object,
                'context' => $context
            ]);
        $this->grav['debugger']->addMessage($statement);
        $this->grav['debugger']->addMessage(json_encode($statement));
        
        return $this->lrs->saveStatement(
            $statement
        );
    }

}
