<?php
namespace EuroLiterie\structureBundle\Classes;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class GestionErreur
{
    private $templating;

    public function __construct($controller, $templating)
    {
        $this->templating = $templating;
        $this->controller = $controller;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        // nous récupérons l'objet exception depuis l'évènement reçu
        $exception = $event->getException();
        $response = new Response();
        if (preg_match('/NotFoundHttpException/',get_class($exception)) == 1)
        {
            $message = $this->controller->routeNotFoundAction($this->templating);
            //$message = $this->templating->render('EuroLiteriestructureBundle:Main:error404.html.twig');
        }else
        {
            $message = $exception->getMessage();
        }
        $response->setContent($message);
        $response->setStatusCode(200);
        $event->setResponse($response);
        //$message = 'My Error says: ' . $exception->getMessage() . ' with code: ' . $exception->getCode();

        //// personnalise notre objet réponse pour afficher les détails de notre exception

        //// HttpExceptionInterface est un type d'exception spécial qui
        //// contient le code statut et les détails de l'entête
        //if ($exception instanceof HttpExceptionInterface) {
            //$response->setStatusCode($exception->getStatusCode());
            //$response->headers->replace($exception->getHeaders());
        //} else {
            //$response->setStatusCode(500);
        //}

        //// envoie notre objet réponse modifié à l'évènement
        //$event->setResponse($response);
    }
    
}
