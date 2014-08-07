<?php namespace Comodojo\DispatcherPlugin;

use \Comodojo\Dispatcher\Template\TemplateBootstrap;

global $dispatcher;

class GitdocPlugin {

    private static $projects = array();

    private static $configuration = "releases.json";

    private $versions = array(
        // "dispatcher"    =>  array("live", "3.0.0"),
        // "core"          =>  array("live", "1.0.0")
    );

    private $current = array(
        // "dispatcher"    =>  "3.0.0",
        // "core"          =>  "1.0.0"
    );

    public function __construct() {

        $configuration = $this->openConfiguration();

        if ( $configuration === false ) return;

        list($projects, $versions, $current) = $this->parseConfiguration($configuration);

        self::$projects = $projects;

        $this->versions = $versions;

        $this->current = $current;

    }

    public static function getProjects() {

        return self::$projects;

    }

    public function routeService($ObjectRequest) {

        $attributes = $ObjectRequest->getAttributes();

        $project = $ObjectRequest->getService();

        $rewrited_attributes = array();

        if ( DISPATCHER_USE_REWRITE ) {

            array_push($rewrited_attributes, $project);

            if ( isset($attributes[0]) ) array_push($rewrited_attributes, $attributes[0]);
            else array_push($rewrited_attributes, $this->current[$project]);

            if ( isset($attributes[1]) ) array_push($rewrited_attributes, $attributes[1]);

        }
        else {

            $rewrited_attributes["project"] = $project;

            if ( isset($attributes['version']) ) $rewrited_attributes["version"] = $attributes['version'];
            else $rewrited_attributes["version"] = $this->current[$project];

            if ( isset($attributes['format']) ) $rewrited_attributes["format"] = $attributes['format'];         

        }

        $ObjectRequest->setAttributes($rewrited_attributes);

        return $ObjectRequest;

    }

    public static function custom_404($ObjectError) {

        $template = new TemplateBootstrap("basic", "superhero");

        $template->setTitle("Comodojo Gitdoc")->setBrand("comodojo/documentation");

        $content = '
            <div class="jumbotron" style="text-align:center;">
                <h1>Project not found!</h1>
                <p class="lead">Have you tried one of following links?</p>
                <p>';

        foreach (self::getProjects() as $project) {
            $content .= '<a class="btn btn-lg btn-success" href="'.DISPATCHER_BASEURL.$project.'/" role="button" style="margin-top:10px;"><span class="glyphicon glyphicon-share"></span>&nbsp;&nbsp;'.$project.'</a>&nbsp;&nbsp;';
        }

        $content .= '</p>

            </div>
        ';

        $template->setContent($content);

        $ObjectError->setContent($template->serialize());

        return $ObjectError;

    }

    private function openConfiguration() {

        $configuration = file_get_contents(DISPATCHER_DOC_FOLDER.self::$configuration);

        if ( $configuration === false ) return false;

        return json_decode($configuration, true);

    }

    private function parseConfiguration($config) {

        $projects = array();

        $versions = array();

        $current = array();

        foreach ($config['projects'] as $project => $data) {
            
            array_push($projects, $project);

            $versions[$project] = array();

            if ( empty($data['latest']) ) {

                array_push($versions[$project], 'live');

                $current[$project] = 'live';

            }
            else {

                array_push($versions[$project], 'live');

                array_push($versions[$project], $data['latest']['version']);

                $current[$project] = $data['latest']['version'];

            }

            foreach ($data['archive'] as $archivedProjectVersion => $archivedProjectData) {
                
                array_push($versions[$project], $archivedProjectVersion);

            }

        }

        return array($projects, $versions, $current);

    }

}

$gp = new GitdocPlugin();

foreach ($gp::getProjects() as $project) {
    
    $dispatcher->setRoute($project, "ROUTE", "vendor/comodojo/dispatcher.servicebundle.gitdoc/services/exhibitor.php", Array("class" => "exhibitor"), false);

    $dispatcher->addHook("dispatcher.request.".$project, $gp, "routeService");

}

$dispatcher->addHook("dispatcher.error.404", $gp, "custom_404");
