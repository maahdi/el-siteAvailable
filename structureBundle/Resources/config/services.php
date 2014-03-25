<?php

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Parameter;

/*

$container->setDefinition(
    'euro_literiestructure.example',
    new Definition(
        'EuroLiterie\structureBundle\Example',
        array(
            new Reference('service_id'),
            "plain_value",
            new Parameter('parameter_name'),
        )
    )
);

*/
$container->setDefinition('literie_gestionMenu',
    new Definition ('EuroLiterie\structureBundle\Classes\GestionMenu',
        array(new Reference('service_container')))
);

//$container
    //->register('kernel.listener.gestionErreur', 'EuroLiterie\structureBundle\Classes\GestionErreur', array(new Reference('templating')))
    //->addTag('kernel.event_listener', array('event' => 'kernel.exception', 'method' => 'onKernelException'));

$listener = new Definition('EuroLiterie\structureBundle\Classes\GestionErreur', 
                array(new Reference('templating')));
$listener->addTag('kernel.event_listener', array('event' => 'kernel.exception', 'method' => 'onKernelException'));
$container->setDefinition('yomaah_exception',$listener);

$menutwig = new Definition('EuroLiterie\structureBundle\Classes\MenuTwigExtension',array(new Reference('literie_gestionMenu')));
$menutwig->addTag('twig.extension');
$container->setDefinition('literie_menuTwigExtension',$menutwig);
