<?php

use Mooc\Export\XmlExport;

/**
 * Controller to export a courseware block tree.
 *
 * @author Christian Flothmann <christian.flothmann@uos.de>
 */
class ExportController extends MoocipController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
    }

    public function index_action()
    {
        $this->set_layout(null);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=courseware.zip');

        // create a temporary directory
        $tempDir = $GLOBALS['TMP_PATH'].'/'.uniqid();
        mkdir($tempDir);

        // dump the XML to the filesystem
        $blockFactory = $this->container['block_factory'];
        $export = new XmlExport($blockFactory);
        $courseware = $this->container['courseware_factory']->makeCourseware($this->container['cid']);
        file_put_contents($tempDir.'/data.xml', $export->export($blockFactory->makeBlock($courseware)));

        $zipFile = $GLOBALS['TMP_PATH'].'/'.uniqid().'.zip';
        create_zip_from_directory($tempDir, $zipFile);

        readfile($zipFile);

        $this->deleteRecursively($tempDir);
        $this->deleteRecursively($zipFile);
    }

    private function deleteRecursively($path)
    {
        if (is_dir($path)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                /** @var SplFileInfo $file */
                if (in_array($file->getBasename(), array('.', '..'))) {
                    continue;
                }

                if ($file->isFile() || $file->isLink()) {
                    unlink($file->getRealPath());
                } else if ($file->isDir()) {
                    rmdir($file->getRealPath());
                }
            }

            rmdir($path);
        } else if (is_file($path) || is_link($path)) {
            unlink($path);
        }
    }
}
 