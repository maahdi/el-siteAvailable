<?php

namespace EuroLiterie\structureBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use EuroLiterie\structureBundle\Entity\HoraireRepo;
use EuroLiterie\structureBundle\Entity\KeywordsRepo;
use Yomaah\structureBundle\Entity\MyMail;
use Yomaah\structureBundle\Interfaces\AjaxInterface;
use Yomaah\ajaxBundle\Controller\AjaxController;

class MainController extends Controller implements AjaxInterface
{
    public function indexAction()
    {
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:index.html.twig');
    }

    public function accueilAction()
    {
        $params = $this->getParams('literie_accueil');
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:accueil.html.twig', $params);
    }

    private function getParams($page)
    {
        $dispatcher = $this->get('bundleDispatcher');
        $params['articles'] = $this->getDoctrine()->getRepository('yomaahBundle:Article')
                ->findByPage(array('pageUrl' => $page,'idSite' => $dispatcher->getIdSite()));
        $keywords = $this->getDoctrine()->getRepository('yomaahBundle:Page')->findKeywords($page, $dispatcher->getIdSite());
        $repoKeyword = new KeywordsRepo();
        $Gkeywords = $repoKeyword->getGeneralKeywords();
        if (!$keywords['keywords'])
        {
            $params['keywords'] = $Gkeywords;
        }else
        {
            $params['keywords'] = $Gkeywords.', '.$keywords['keywords']; 
        }
        $page = $this->getDoctrine()->getRepository('yomaahBundle:Page')->findPageByUrl(array('pageUrl' => $page, 'idSite' => $dispatcher->getIdSite()));
        $params['position'] = $page->getPosition();
        return $params;
    }

    /*
     * Possibilité de mettre sa en base ou yml pour changer dynamiquement
     */
    private function getPosition($page)
    {
        $positions = array('accueil' => 'Accueil',
            'marques' => 'Nos Marques',
            'contact' => 'Nous Trouver',
            'magasin' => 'Notre magasin');
        return $positions[$page];
        
    }

    public function marquesAction()
    {
        $params = $this->getParams('literie_marques');
        $params['marques'] = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:Marque')->findAll();
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:marques.html.twig', $params);
    }

    public function magasinAction()
    {
        $params = $this->getParams('literie_magasin');
        $params['images'] = AjaxController::imageSearch('galerie/magasin/active', 'EuroLiterie/structureBundle');
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:magasin.html.twig',$params);
    }

    public function contactAction()
    {
        $mail =  new MyMail();
        $form = $this->getForm($mail);
        $request = $this->get('request');
        $envoi = false;
        if ($request->getMethod() == 'POST')
        {
            $form->bind($request);
            if ($form->isValid())
            {
                $m = $mail->getSwiftMailer();
                $this->get('mailer')->send($m); 
                $mail =  new MyMail();
                $form = $this->getForm($mail);
                $this->get('session')->set('envoie', true);
                return $this->redirect($this->generateUrl('literie_contact'));
            }
        }
        if ($this->get('session')->has('envoie'))
        {
            $this->get('session')->remove('envoie');
            $envoi = true;
        }
        $params = $this->getParams('literie_contact');
        $tmp = array();
        foreach ($params['articles'] as $article)
        {
            if ($article->getTagName() == 'adresse_courrier' || $article->getTagName() == 'adresse_phone')
            {
                $tmp[] = $article;
            }
        }
        $params['adresse'] = $tmp;
        $h = new HoraireRepo();
        $params['horaires'] = $h->getHoraires();
        $params['form'] = $form->createView();
        $params['envoie'] = $envoi;
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:contact.html.twig', $params);
    }

    public function atteindreAction(Array $param)
    {
        $url = $param['url'];
        if ($param['lien'] == 'marquesAdmin')
        {
            $url .= '/admin_marques';

        }else if ($param['lien'] == 'magasinAdmin' || $param['lien'] == 'equipeAdmin')
        {
            $url .= '/admin_magasin';

        }else
        {
            $url .= '/admin_accueil';
        }
        return new Response($url);
    }

    private function getForm($mail)
    {
        $formBuilder = $this->createFormBuilder($mail);
        $formBuilder->add('Objet','text',array('attr' => array ('placeholder' => 'L\'objet de votre message'),'label' => 'Sujet de votre message :'))
                    ->add('From','email',array('attr' => array ('placeholder' => 'Votre adresse email'),'label' => 'Votre email :'))
                    ->add('Message','textarea',array('attr' => array ('placeholder' => 'Votre message ...'),'label' => 'Votre message :'));
        return $formBuilder->getForm();
    }

    /**
     * Remplace fonction de login
     * de Yomaah\connexionBundle
     **/
    public function adminAction()
    {
        $this->get('session')->set('zoneAdmin', true);
        return $this->redirect($this->generateUrl('admin_literie_accueil'),301);
    }

    public function decoadminAction()
    {
        $this->get('session')->remove('zoneAdmin');
        return $this->redirect($this->generateUrl('literie_accueil'),301);
    }

    public function getRepoAdminContentList($object)
    {
        $modifiable = array('marquesAdmin' => 'Marque',
            'promotionsAdmin' => 'Promotion',
            'pagesAdmin' => 'Page');
        if (array_key_exists($object, $modifiable))
        {
            return $modifiable[$object];

        }else
        {
            return false;
        }
    }

    public function saveElementAction(Array $param)
    {
        if (($repo = $this->getRepoAdminContentList($param['lien'])) != false)
        {
            $element = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->find($param['id']); 
            $em = $this->getDoctrine()->getManager();
            if ($param['input'] != null)
            {
                foreach($param['input'] as $key => $input)
                {
                    $element->$key($input);
                }
            }
            if ($param['textarea'] != null)
            {
                foreach($param['textarea'] as $key => $input)
                {
                    $element->$key($input);
                }
            }
            $em->persist($element);
            $em->flush();
            return new Response();

        }else if ($param['lien'] == 'sliderAdmin')
        {
            return $this->saveSlider($param['active'], $param['inactive'], 'slider');

        }else if ($param['lien'] == 'magasinAdmin')
        {
            return $this->saveSlider($param['active'], $param['inactive'], 'galerie/magasin');

        }else if ($param['lien'] == 'equipeAdmin')
        {
            $element = $this->GetDoctrine()->getRepository('yomaahBundle:Article')->find($param['id']);
            foreach ($param['input'] as $key => $input)
            {
                $element->$key($input);
            }
            $em = $this->getDoctrine()->getManager();
            $em->persist($element);
            $em->flush();
            return new Response();
        }
    }

    public function deleteElementAction(Array $param)
    {
        if (($repo = $this->getRepoAdminContentList($param['lien'])) != false)
        {
            $em = $this->getDoctrine()->getManager();
            $element = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->find($param['id']);
            $em->remove($element);
            $em->flush();
            return new Response();
        }
    }

    public function addElementAction(Array $param)
    {
        if (($repo = $this->getRepoAdminContentList($param['lien'])) != false)
        {
            $element = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->getNew();
            return new JsonResponse($element);
        }
    }

    private function getDeployedImagesUrl()
    {
        $dispatcher = $this->get('bundleDispatcher');
        if ($dispatcher->getDeployed())
        {
            return '../bundles/euroliteriestructure/images/';
        }else
        {
            return '../../bundles/euroliteriestructure/images/';
        }
    }

    public function logoAdminStructureAction(Array $param)
    {
        if ($param['lien'] == 'equipeAdmin')
        {
            return new Response('<section class="logoGalerie">
                    <input type="hidden" value="pngUrl" />
                    <figure class="adminMarqueLogo"><img src="'.$this->getDeployedImagesUrl().'galerie/equipe/pngUrl"></img></figure>
                    <input type="checkbox" name="check" />
                </section>');
            
        }else if ($param['lien'] == 'marquesAdmin')
        {
            return new Response('<section class="logoGalerie">
                    <input type="hidden" value="pngUrl" />
                    <figure class="adminMarqueLogo"><img src="'.$this->getDeployedImagesUrl().'marques/pngUrl"></img></figure>
                    <input type="checkbox" name="check" />
                </section>');
            
        }
        
    }

    public function getAdminContentStructureAction(Array $param)
    {
        $dispatcher = $this->get('bundleDispatcher');
        if ($dispatcher->isAdmin())
        {
            if (($repo = $this->getRepoAdminContentList($param['lien'])) != false)
            {
                $response = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->getHtml($this->getDeployedImagesUrl()); 

            }else if ($param['lien'] == 'GkeywordsAdmin')
            {
                $keyRepo = new KeywordsRepo();
                $response = $keyRepo->getHtml();

            }else if ($param['lien'] == 'sliderAdmin' || $param['lien'] == 'magasinAdmin')
            {
                $response = $this->get('templating')
                    ->render('EuroLiteriestructureBundle:Ajax:imagesSlider.html.twig',array('url' => $this->getDeployedImagesUrl()));

            }else if ($param['lien'] == 'sliderMiniature')
            {
                $response = '<article class="sliderImage">
                        <input type="hidden" value="" />
                        <figure><img src=""></img></figure>
                        <input type="checkbox" name="check"/>
                    </article>';

            }else if ($param['lien'] == 'equipeAdmin')
            {
                $response = $this->get('templating')->render('EuroLiteriestructureBundle:Ajax:equipe.html.twig');
            }           
        }else if ($param['lien'] == 'promoInfo')
        {
            $promo = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:Promotion')->find($param['id']);
            $response = '<div><p>'.$promo->getPromoInfo().'</p></div>';
        }
        return new Response($response);
    }

    public function getDialogAction(Array $param)
    {
        if (($repo = $this->getRepoAdminContentList($param['lien'])) != false)
        {
            $filename = $param['dialog'].$repo;
            
        }else if ($param['lien'] == 'sliderAdmin' || $param['lien'] == 'magasinAdmin')
        {
            $filename = $param['dialog'].'Slider';

        }else if ($param['lien'] == 'equipeAdmin')
        {
            $filename = $param['dialog'].'Equipe';
        }
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Dialog:'.$filename.'.html.twig');
    }

    public function getAdminInterfaceAction(Array $param)
    {
        if (($filename = $this->getRepoAdminContentList($param['lien'])) != false)
        {
            $fichier = 'adminInterface'.$filename.'.html.twig';
            
        }else if ($param['lien'] == 'sliderAdmin')
        {
            $fichier = 'adminInterfaceSlider.html.twig';

        }else if ($param['lien'] == 'magasinAdmin')
        {
            $fichier = 'adminInterfaceMagasin.html.twig';

        }else if ($param['lien'] == 'equipeAdmin')
        {
            $fichier = 'adminInterfaceEquipe.html.twig';
        }
        return  $this->get('templating')->renderResponse('EuroLiteriestructureBundle:AdminMenu:'.$fichier);

    }

    public function getAdminContentAction(Array $param)
    {
        if (($repo = $this->getRepoAdminContentList($param['lien'])) != false)
        {
            $response = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->findAll(); 
            return new JsonResponse($response);

        }else if ($param['lien'] == 'sliderAdmin')
        {
            $slider = array();
            $slider['active'] = AjaxController::imageSearch('slider/active', 'EuroLiterie/structureBundle');
            $slider['inactive'] = AjaxController::imageSearch('slider/inactive', 'EuroLiterie/structureBundle');
            $slider['struct'] = '<article class="sliderImage">
                    <input type="hidden" value="%imgUrl%" />
                    <figure><img src="'.$this->getDeployedImagesUrl().'slider/%dossier%/%imgUrl%"></img></figure>
                    <input type="checkbox" name="check"/>
                </article>';
            return new JsonResponse($slider);

        }else if ($param['lien'] == 'magasinAdmin')
        {
            $image = array();
            $image['active'] = AjaxController::imageSearch('galerie/magasin/active', 'EuroLiterie/structureBundle');
            $image['inactive'] = AjaxController::imageSearch('galerie/magasin/inactive', 'EuroLiterie/structureBundle');
            $image['struct'] = '<article class="sliderImage">
                    <input type="hidden" value="%imgUrl%" />
                    <figure><img src="'.$this->getDeployedImagesUrl().'galerie/magasin/%dossier%/%imgUrl%"></img></figure>
                    <input type="checkbox" name="check"/>
                </article>';
            return new JsonResponse($image);

        }else if ($param['lien'] == 'equipeAdmin')
        {
            $dispatcher = $this->get('bundleDispatcher');
            $param['idSite'] = $dispatcher->getIdSite();
            $param['tag'] = 'equipe';
            $response = $this->getDoctrine()->getRepository('yomaahBundle:Article')->findByTag($param);
            return new JsonResponse($response);
        }

    }

    public function deleteLogoAction($param)
    {
        switch ($param['lien'])
        {
        case 'marquesAdmin':
            $dossier = 'marques';
            break;
        case 'equipeAdmin':
            $dossier = 'galerie/equipe';
            break;
        }
        if ($param['png'] != false)
        {
            if (!(AjaxController::testFileExists('../deleted/'.$dossier, $png)))
            {
                AjaxController::moveImage('marques/', '..deleted/'.$dossier.'/', $png);
            }else
            {
                AjaxController::moveImage('marques/', '..deleted/'.$dossier.'/'.time().'_', $png);
            }
            return new Response();
        }
    }

    public function saveSlider($active, $inactive, $folder)
    {
        if (is_array($active))
        {
            foreach ($active as $file)
            {
                if (AjaxController::testNameValide($file, 'jpg|png|jpeg'))
                {
                    if (!(AjaxController::testFileExists('./bundles/euroliteriestructure/images/'.$folder.'/active/', $file))
                        && AjaxController::testFileExists('./bundles/euroliteriestructure/images/'.$folder.'/inactive/', $file))
                    {
                        AjaxController::moveImage($folder.'/inactive/', $folder.'/active/', $file, 'euroliteriestructure');
                    }
                }
            }
        }else
        {
            AjaxController::moveImage($folder.'/active/', $folder.'/inactive/', '*');
        }
        if (is_array($inactive))
        {
            foreach($inactive as $file)
            {
                if (AjaxController::testNameValide($file, 'png|jpg|jpeg'))
                {
                    if (!(AjaxController::testFileExists('./bundles/euroliteriestructure/images/'.$folder.'/inactive/', $file))
                        && AjaxController::testFileExists('./bundles/euroliteriestructure/images/'.$folder.'/active/', $file))
                    {
                        AjaxController::moveImage($folder.'/active/', $folder.'/inactive/', $file, 'euroliteriestructure');

                    }else if (AjaxController::testFileExists('./bundles/euroliteriestructure/images/'.$folder.'/inactive', $file))
                    {
                        AjaxController::moveImage($folder.'/active/', $folder.'/inactive/', $file, 'euroliteriestructure', time().$file);
                    }
                }
            }
        }
        return new Response();
    }

    public function saveImageAction(Array $param)
    {
        if (($repo = self::getRepoAdminContentList($param['lien'])) != false)
        {
            $marques = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->find($param['id']);
            if ($param['png'] != false)
            {
                $marques->setPngUrl($param['png']);
            }
            $em = $this->getDoctrine()->getManager();
            $em->persist($marques);
            $em->flush();
            return new Response();

        }else if ($param['lien'] == 'equipeAdmin')
        {
            $article = $this->getDoctrine()->getRepository('yomaahBundle:Article')->findOneBy(array('artId' => $param['id']));
            if ($param['png'] != false)
            {
                $article->getPng()->setPngUrl($param['png']);
                $em = $this->getDoctrine()->getManager();
                $em->persist($article);
                $em->flush();
                return new Response();
            }
            
        }
    }
    public function uploadImageAction(Array $param)
    {
        $file = $param['file'];
        $maxSize = $param['fileInfo']['maxSize'];
        $maxW = $param['fileInfo']['maxW'];
        $colorR = $param['fileInfo']['colorR'];
        $colorG = $param['fileInfo']['colorG'];
        $colorB = $param['fileInfo']['colorB'];
        $maxH = $param['fileInfo']['maxH'];
        $img = array();
        $tmp = explode('Controller',__DIR__);
        if ($param['lien'] == 'marquesAdmin')
        {
            $img = $this->uploadLogoAction($file, $maxSize, $maxW, $colorR, $colorG, $colorB, $maxH);

        }else if ($param['lien'] == 'sliderAdmin')
        {
            $folder = $tmp[0].'Resources/public/images/slider/inactive/';
            $img = $this->uploadSliderAction($file, $maxSize, $maxW, $colorR, $colorG, $colorB, $maxH, $folder);

        }else if ($param['lien'] == 'magasinAdmin')
        {
            $folder = $tmp[0].'Resources/public/images/galerie/magasin/inactive/';
            $img = $this->uploadSliderAction($file, $maxSize, $maxW, $colorR, $colorG, $colorB, $maxH, $folder);

        }else if ($param['lien'] == 'equipeAdmin')
        {
            $folder = $tmp[0].'Resources/public/images/galerie/equipe/';
            $img = $this->uploadSliderAction($file, $maxSize, $maxW, $colorR, $colorG, $colorB, $maxH, $folder);
        }

        if($img['imgUploaded'] === true){
            return new Response ('<img src="../bundles/euroliteriestructure/images/success.gif" width="16" height="16" border="0" style="marin-bottom: -4px;" /> Success!<br /><img src="'.$img['image'].'" border="0" />');
        }else{
            $response = '<img src="../bundles/euroliteriestructure/images/error.gif" width="16" height="16px" border="0" style="marin-bottom: -3px;" /> Error(s) Found: ';
            foreach($img['errorList'] as $value){
                    $response .= $value.', ';
            }
            return new Response ($response);
        }
    }

    public function uploadSliderAction($file, $maxSize, $maxW, $colorR, $colorG, $colorB, $maxH, $folder)
    {
        $filesize_image = $file->getClientSize();
        if($filesize_image > 0){
            $upload_image = AjaxController::uploadImage('euroliteriestructure', $file, $maxSize, $maxW, $folder, $colorR, $colorG, $colorB, $maxH, true);
            if(is_array($upload_image)){
                foreach($upload_image as $key => $value) {
                    if($value == "-ERROR-") {
                        unset($upload_image[$key]);
                    }
                }
                $document = array_values($upload_image);
                for ($x=0; $x<sizeof($document); $x++){
                    $errorList[] = $document[$x];
                }
                $imgUploaded = false;
            }else{
                $imgUploaded = true;
            }
        }else{
            $imgUploaded = false;
            $errorList[] = "File Size Empty";
        }
        if ($imgUploaded)
        {
            return array('imgUploaded' => true, 'image' => $upload_image);
        }else
        {
            return array('imgUploaded' => false, 'errorList' => $errorList);
        }
    }
    public function deleteImageAction(Array $param)
    {
        if ($param['lien'] == 'marquesAdmin')
        {
            $dossier = 'marques/';
            AjaxController::deleteImage($dossier, $dossier, $param['png'], 'euroliteriestructure');

        }else if ($param['lien'] == 'sliderAdmin' || $param['lien'] == 'magasinAdmin')
        {
            if ($param['lien'] == 'sliderAdmin')
            {
                $dossier = 'slider/';

            }else if ($param['lien'] == 'magasinAdmin')
            {
                $dossier = 'galerie/magasin/';
            }
            $tmp = array();
            foreach ($param['png'] as $png)
            {
                if (AjaxController::testNameValide($png, 'jpg|jpeg|png'))
                {
                    if (AjaxController::testFileExists('./bundles/euroliteriestructure/images/'.$dossier.'active/', $png))
                    {
                        $dossierFile = $dossier.'active/';

                    }else if (AjaxController::testFileExists('./bundles/euroliteriestructure/images/'.$dossier.'inactive/', $png))
                    {
                        $dossierFile = $dossier.'inactive/';
                    }
                    AjaxController::deleteImage($dossier, $dossierFile, $png, 'euroliteriestructure');
                }
            }
        }else if ($param['lien'] == 'equipeAdmin')
        {
            $dossier = 'galerie/equipe/';
            AjaxController::deleteImage($dossier, $dossier, $param['png'], 'euroliteriestructure');
        }
        return new Response();
    }

    public function uploadLogoAction($file, $maxSize, $maxW, $colorR, $colorG, $colorB, $maxH)
    {
        $tmp = explode('Controller',__DIR__);
        $folder = $tmp[0].'Resources/public/images/marques/';
        $filesize_image = $file->getClientSize();
        if($filesize_image > 0){
            $upload_image = AjaxController::uploadImage('euroliteriestructure', $file, $maxSize, $maxW, $folder, $colorR, $colorG, $colorB, $maxH);
            if(is_array($upload_image)){
                foreach($upload_image as $key => $value) {
                    if($value == "-ERROR-") {
                        unset($upload_image[$key]);
                    }
                }
                $document = array_values($upload_image);
                for ($x=0; $x<sizeof($document); $x++){
                    $errorList[] = $document[$x];
                }
                $imgUploaded = false;
            }else{
                $imgUploaded = true;
            }
        }else{
            $imgUploaded = false;
            $errorList[] = "File Size Empty";
        }
        if ($imgUploaded)
        {
            return array('imgUploaded' => true, 'image' => $upload_image);
        }else
        {
            return array('imgUploaded' => false, 'errorList' => $errorList);
        }
    }
}
