<?php
namespace Sledgehammer\Devutils;

use Sledgehammer\Mvc\Component\Breadcrumbs;
use Sledgehammer\Mvc\FileDocument;
use Sledgehammer\Mvc\Template;
use Sledgehammer\Mvc\Folder;

/**
 * Generate and show API documentation.
 */
class PhpDocs extends Folder
{
    /**
     * @var Module|Project
     */
    private $object;

    public function __construct($object)
    {
        parent::__construct();
        $this->object = $object;
    }

    public function index()
    {
        $url = URL::getCurrentURL();
        $url->query = array();
        $target_path = $this->documentationPath($this->object);
        Breadcrumbs::instance()->add('API Documenation');

        // Controleer of er reeds documentatie gegenereerd is
        $generate = false;
        $age = null;
        if (value($_GET['refresh']) || !file_exists($target_path.'index.html')) { // No generated documentation found?
            $generate = $this->buildGenerator($target_path);
            $url->query['refresh'] = 0;
        } else {
            $url->query['refresh'] = 1;
            $documentation_age = $this->documentationAge() / 3600; // leeftijd in uur
            if ($documentation_age > 8) {
                $age = round($documentation_age / 24).' days '.round($documentation_age % 24).' hours';
            } else {
                $age = round($documentation_age, 1).' hours';
            }
        }

        // Toon de gegenereerde documentatie
        return new Template('phpdocs.php', array(
            'generate' => $generate,
            'url' => $url,
            'age' => $age,
            'src' => $this->getPath().'overview.html',
        ), array(
            'title' => 'API Documentation',
        ));
    }

    public function overview()
    {
        return $this->staticFile('index.html');
    }
    public function file($filename)
    {
        return $this->staticFile($filename);
    }
    public function folder($folder, $filename = null)
    {
        if ($filename !== false) {
            return $this->staticFile($folder.'/'.$filename);
        }
        $path = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($this->getPath()));

        return $this->staticFile($path);
    }

    private function staticFile($path)
    {
        return new FileDocument($this->documentationPath().$path);
    }

    /**
     * Geeft de map terug waar de gegenereerde documentatie wordt opgeslagen.
     *
     * @param Module|Project $object
     */
    private function documentationPath()
    {
        return TMP_DIR.'phpdocs/'.$this->object->identifier.'/';
    }

    /**
     * @return int het aantal seconden dat de documentatie oud is
     */
    private function documentationAge()
    {
        $target_path = $this->documentationPath($this->object);
        if (!file_exists($target_path.'index.html')) {
            return true;
        }
        $time = filectime($target_path.'index.html');

        return time() - $time;
    }

    private function buildGenerator($target_path)
    {
        // module of project instellingen laden
        $source_path = $this->object->path;

        $directories = array();
        $files = array();
        $type = get_class($this->object);
        switch ($type) {

            case 'Sledgehammer\Module':
                $directories[] = $source_path.'classes';
                $files[] = $source_path.'init.php';
                $files[] = $source_path.'functions.php';
                break;

            case 'Sledgehammer\Project':
                $directories[] = $source_path.'application/classes';
                $files[] = $source_path.'application/init.php';
                $files[] = $source_path.'application/functions.php';
                foreach ($this->object->modules as $Module) {
                    $directories[] = $Module->path.'classes';
                    $files[] = $Module->path.'init.php';
                    $files[] = $Module->path.'functions.php';
                }
                break;

            default:
                error('Unknown type: '.$type);

        }
        // PhpDocumentor ini-file genereren
        $phpdocs_ini = ";; Generated by dev_utils PhpDocs.php\n\n";
        $phpdocs_ini .= "sourcecode = off\n";
        $phpdocs_ini .= "output = HTML:frames:DOM/earthli\n\n";
//		$phpdocs_ini .= "output = HTML:Smarty:PHP\n\n";
        $phpdocs_ini .= 'target = '.$target_path."\n";
        $phpdocs_ini .= 'directory = '.implode(',', $directories)."\n";
        $phpdocs_ini .= 'filename = '.implode(',', $files)."\n";

        mkdirs($target_path);
        file_put_contents($target_path.'dev_utils.ini', $phpdocs_ini);

        // prepare PhpDocumentor builder script
        $url = URL::getCurrentURL();
        $url->path = WEBPATH.'phpdocumentor/docbuilder/builder.php';
        $url->query = array(
            'interface' => 'web',
            'dataform' => 'true',
            'setting_useconfig' => $target_path.'dev_utils',
        );

        return new PHPFrame($url);
    }
}
