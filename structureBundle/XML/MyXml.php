<?php
namespace EuroLiterie\structureBundle\XML;

abstract class MyXml
{
    private function getPath($file)
    {
        return 'EuroLiterie/structureBundle/XML/'.$file.'.xml';
    }

    protected function getXmlFilePath($file)
    {
        $dir = preg_split('/\/src/',__DIR__);
        return $dir[0].'/src/'.$this->getPath($file);
    }
    abstract function save($modified);
}
