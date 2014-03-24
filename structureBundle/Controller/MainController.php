<?php

namespace EuroLiterie\structureBundle\Controller;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use EuroLiterie\structureBundle\Entity\HoraireRepo;
use EuroLiterie\structureBundle\Entity\KeywordsRepo;
use Yomaah\structureBundle\Entity\MyMail;

class MainController extends Controller
{
    //private $keywords = 'euroliterie, matelats, sommier, vente, literie, lits électriques';

    public function indexAction()
    {
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:index.html.twig');
    }

    public function accueilAction()
    {
        //$articles = $this->getDoctrine()->getRepository('yomaahBundle:Article')->findByPage('accueil',1);
        $promotions = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:Promotion')->findBy(array('tag'=>'periode'), array('dateDebut' => 'asc'));
        $i =0;
        $actuel = array();
        $avenir = array();
        foreach ($promotions as $promo)
        {
            if ($promo->getActuel())
            {
                $actuel[] = $promo;
                $i++;
            }
            //else
            //{
                //$avenir[] = $promo;
            //}
        }
        if ($i == 0)
        {
            $actuel = false;
        }
        //if (count($avenir) ==0)
        //{
            //$avenir = false;
        //}
        $params = $this->getParams('accueil');
        $params['actuel'] = $actuel;
        $file = $this->findFile('Resources/public/images/slider/active');
        if (count($file) < 5)
        {
            $params['slider'] = $this->findFile('Resources/public/images/slider/active');
        }else
        {
            for ($i = 0; $i < 4; $i++)
            {
                $tmpFile[] = $file[$i];
            }
            $params['slider'] = $tmpFile;
        }
        //$params['avenir'] = $avenir;
        //return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:accueil.html.twig',
            //array('position' => 'Accueil','articles' => $articles,'actuel' => $actuel,'avenir' => $avenir));
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:accueil.html.twig', $params);
    }

    private function getParams($page)
    {
        $params['articles'] = $this->getDoctrine()->getRepository('yomaahBundle:Article')->findByPage($page);
        $keywords = $this->getDoctrine()->getRepository('yomaahBundle:Page')->findKeywords($page);
        $repoKeyword = new KeywordsRepo();
        $Gkeywords = $repoKeyword->getGeneralKeywords();
        if (!$keywords['keywords'])
        {
            $params['keywords'] = $Gkeywords;
        }else
        {
            $params['keywords'] = $Gkeywords.', '.$keywords['keywords']; 
        }
        //$params['position'] = $this->getPosition($page);
        $page = $this->getDoctrine()->getRepository('yomaahBundle:Page')->findBy(array('pageUrl' => $page));
        $params['position'] = $page[0]->getPosition();
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
        $params = $this->getParams('marques');
        $params['marques'] = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:Marque')->findAll();
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:marques.html.twig', $params);
    }

    public function magasinAction()
    {
        $params = $this->getParams('magasin');
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
        $params = $this->getParams('contact');
        $h = new HoraireRepo();
        $params['horaires'] = $h->getHoraires();
        $params['form'] = $form->createView();
        $params['envoie'] = $envoi;
        return $this->get('templating')->renderResponse('EuroLiteriestructureBundle:Main:contact.html.twig', $params);
    }

    private function getForm($mail)
    {
        $formBuilder = $this->createFormBuilder($mail);
        $formBuilder->add('Objet','text',array('attr' => array ('placeholder' => 'L\'objet de votre message'),'label' => 'Sujet de votre message :'))
                    ->add('From','email',array('attr' => array ('placeholder' => 'Votre adresse email'),'label' => 'Votre email :'))
                    ->add('Message','textarea',array('attr' => array ('placeholder' => 'Votre message ...'),'label' => 'Votre message :'));
        return $formBuilder->getForm();
    }


    public function getAdminContentAction($object)
    {
        if ($this->get('security.context')->isGranted('ROLE_USER'))
        {
            if ($object == 'sliderAdmin')
            {
                $slider = array();
                $slider['active'] = $this->findFile('Resources/public/images/slider/active');
                $slider['inactive'] = $this->findFile('Resources/public/images/slider/inactive');
                $slider['struct'] = '<article class="sliderImage">
                        <input type="hidden" value="%imgUrl%" />
                        <figure><img src="../bundles/euroliteriestructure/images/slider/%dossier%/%imgUrl%"></img></figure>
                        <input type="checkbox" name="check"/>
                    </article>';
                return new JsonResponse($slider);
            }else if (($repo =self::getRepoAdminContentList($object))!= false)
            {
                $response = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->findAll(); 
                return new JsonResponse($response);
            }
        }
    }
    public function getAdminContentStructureAction($object)
    {
        if ($this->get('security.context')->isGranted('ROLE_USER'))
        {
            if (($repo =$this->getRepoAdminContentList($object))!= false)
            {
                $response = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->getHtml(); 
                return new Response($response);
            }
        }
    }

    static function getRepoAdminContentList($object)
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

    public function saveElementAction($input, $textarea, $id, $object)
    {
        if ($this->get('security.context')->isGranted('ROLE_USER'))
        {
            $elem = explode('&',$input);
            $obj = array();
            foreach($elem as $e)
            {
                $tmp = explode('=',$e);
                if (preg_match('/date/',urldecode($tmp[0])) == 1)
                {
                    $date = preg_replace('/\//','-',urldecode($tmp[1]));
                    $obj['set'.ucfirst($tmp[0])] = new \Datetime($date);
                    
                }else
                {
                    $obj['set'.ucfirst($tmp[0])] = urldecode($tmp[1]);
                }
            }
            $elem = null;
            if ($textarea != null)
            {
                $elem = explode('&', $textarea);
                foreach($elem as $e)
                {
                    $tmp = explode('=', $e);
                    $obj['set'.ucfirst($tmp[0])]= urldecode($tmp[1]);
                }
                $elem = null;
            }
            if (($repo = $this->getRepoAdminContentList($object)) != false)
            {
                $element = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->find($id); 
                foreach($obj as $key=>$val)
                {
                    $element->$key($val);
                }
                $em = $this->getDoctrine()->getManager();
                $em->persist($element);
                $em->flush();
                return new Response();
            }
        }
    }

    public function deleteElementAction($id, $object)
    {
        if ($this->get('security.context')->isGranted('ROLE_USER'))
        {
            if (($repo = self::getRepoAdminContentList($object)) != false)
            {
                $em = $this->getDoctrine()->getManager();
                $element = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->find($id);
                $em->remove($element);
                $em->flush();
                return new Response();
            }
        }
    }

    public function addElementAction($object)
    {
        if ($this->get('security.context')->isGranted('ROLE_USER'))
        {
            if (($repo = self::getRepoAdminContentList($object)) != false)
            {
                $element = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->getNew();
                return new JsonResponse($element);
            }
        }
    }

    public function imagesAdminStructureAction()
    {
        return new Response('<section class="logoGalerie">
                    <input type="hidden" value="pngUrl" />
                    <figure class="adminMarqueLogo"><img src="../bundles/euroliteriestructure/images/marques/pngUrl"></img></figure>
                    <input type="checkbox" name="check" />
                </section>');
        
    }
    private function findFile($rootDir)
    {
        $tmp = explode('Controller',__DIR__);
        $baseDir = $tmp[0];
        $finder = new Finder();
        $f = $finder->depth('== 0')->files()->notname('/~$/')->in($baseDir.$rootDir);
        if (count($f) > 0)
        {
            $tmp = array();
            foreach ($f as $file)
            {
                $tmp[] = explode ($baseDir.$rootDir.'/', $file);
            }
            foreach ($tmp as $file)
            {
                $files[] = $file[1];
            }
            return $files;
        }
    }

    public function imagesAdminAction($objet)
    {
        $subject = explode('Admin', $objet);
        $rootDir = 'Resources/public/images/'.$subject[0];
        return new JsonResponse($this->findFile($rootDir));
    }

    public function saveImageAction($id, $png, $elem)
    {
        if ($this->get('security.context')->isGranted('ROLE_USER'))
        {
            if (($repo = self::getRepoAdminContentList($elem)) != false)
            {
                $marques = $this->getDoctrine()->getRepository('EuroLiteriestructureBundle:'.$repo)->find($id);
                $marques->setPngUrl($png);
                $em = $this->getDoctrine()->getManager();
                $em->persist($marques);
                $em->flush();
                return new Response();
            }
        }
    }
    public function uploadSliderAction($request, $file)
    {
        $maxSize = strip_tags($request['maxSize']);
        $maxW = strip_tags($request['maxW']);
        $colorR = strip_tags($request['colorR']);
        $colorG = strip_tags($request['colorG']);
        $colorB = strip_tags($request['colorB']);
        $maxH = strip_tags($request['maxH']);
        $tmp = explode('Controller',__DIR__);
        $folder = $tmp[0].'Resources/public/images/slider/inactive/';
        $filesize_image = $file->getClientSize();
        if($filesize_image > 0){
            $upload_image = $this->uploadImage($file, $maxSize, $maxW, $folder, $colorR, $colorG, $colorB, $maxH, true);
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
        if($imgUploaded){
            return new Response ('<img src="../bundles/euroliteriestructure/images/success.gif" width="16" height="16" border="0" style="marin-bottom: -4px;" /> Success!<br /><img src="'.$upload_image.'" border="0" />');
        }else{
            $response = '<img src="../bundles/euroliteriestructure/images/error.gif" width="16" height="16px" border="0" style="marin-bottom: -3px;" /> Error(s) Found: ';
            foreach($errorList as $value){
                    $response .= $value.', ';
            }
            return new Response ($response);
        }
    }

    public function uploadLogoAction($request, $file)
    {
        $maxSize = strip_tags($request['maxSize']);
        $maxW = strip_tags($request['maxW']);
        $colorR = strip_tags($request['colorR']);
        $colorG = strip_tags($request['colorG']);
        $colorB = strip_tags($request['colorB']);
        $maxH = strip_tags($request['maxH']);
        $tmp = explode('Controller',__DIR__);
        $folder = $tmp[0].'Resources/public/images/marques/';
        $filesize_image = $file->getClientSize();
        if($filesize_image > 0){
            $upload_image = $this->uploadImage($file, $maxSize, $maxW, $folder, $colorR, $colorG, $colorB, $maxH);
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
        if($imgUploaded){
            return new Response ('<img src="../bundles/euroliteriestructure/images/success.gif" width="16" height="16" border="0" style="marin-bottom: -4px;" /> Success!<br /><img src="'.$upload_image.'" border="0" />');
        }else{
            $response = '<img src="../bundles/euroliteriestructure/images/error.gif" width="16" height="16px" border="0" style="marin-bottom: -3px;" /> Error(s) Found: ';
            foreach($errorList as $value){
                    $response .= $value.', ';
            }
            return new Response ($response);
        }
    }

	private function uploadImage($file, $maxSize, $maxW, $folder, $colorR, $colorG, $colorB, $maxH = null, $resize = null){
		$maxlimit = $maxSize;
		$allowed_ext = "jpg,jpeg,gif,png,bmp";
        $errorList = array();
		$match = "";
        $filesize = $file->getClientSize();
        $tmp = explode('/public/', $folder);
        $fullPath = '../bundles/euroliteriestructure/'.$tmp[1];
		if($filesize > 0){	
			$filename = strtolower($file->getClientOriginalName());
			$filename = preg_replace('/\s/', '_', $filename);
		   	if($filesize < 1){ 
				$errorList[] = "File size is empty.";
			}
			if($filesize > $maxlimit){ 
				$errorList[] = "File size is too big.";
			}
		    $file_ext = preg_split("/\./",$filename);
			$allowed_ext = preg_split("/\,/",$allowed_ext);
			foreach($allowed_ext as $ext){
				if($ext==end($file_ext)){
	   				$match = "1"; // File is allowed
					$NUM = time();
					$front_name = substr($file_ext[0], 0, 15);
					$newfilename = $filename;
					$filetype = end($file_ext);
					$save = $folder.$newfilename;
					if(!file_exists($save)){
						list($width_orig, $height_orig) = getimagesize($file->getPathName());
						if($maxH == null){
							if($width_orig < $maxW){
								$fwidth = $width_orig;
							}else{
								$fwidth = $maxW;
							}
							$ratio_orig = $width_orig/$height_orig;
							$fheight = $fwidth/$ratio_orig;
							
							$blank_height = $fheight;
							$top_offset = 0;
								
                        }else{
                            if ($resize)
                            {
                                $fwidth = $maxW;
                                $fheight = $maxH;
                            }else if($width_orig <= $maxW && $height_orig <= $maxH){
								$fheight = $height_orig;
								$fwidth = $width_orig;
                            }else{
								if($width_orig > $maxW){
									$ratio = ($width_orig / $maxW);
									$fwidth = $maxW;
									$fheight = ($height_orig / $ratio);
									if($fheight > $maxH){
										$ratio = ($fheight / $maxH);
										$fheight = $maxH;
										$fwidth = ($fwidth / $ratio);
									}
                                }
                                if($height_orig > $maxH){
									$ratio = ($height_orig / $maxH);
									$fheight = $maxH;
									$fwidth = ($width_orig / $ratio);
									if($fwidth > $maxW){
										$ratio = ($fwidth / $maxW);
										$fwidth = $maxW;
										$fheight = ($fheight / $ratio);
									}
								}
							}
							if($fheight == 0 || $fwidth == 0 || $height_orig == 0 || $width_orig == 0){
								die("FATAL ERROR REPORT ERROR CODE [add-pic-line-67-orig] to <a href='http://www.atwebresults.com'>AT WEB RESULTS</a>");
							}
							if($fheight < 45){
								$blank_height = 45;
								$top_offset = round(($blank_height - $fheight)/2);
							}else{
								$blank_height = $fheight;
							}
						}
						$image_p = imagecreatetruecolor($fwidth, $blank_height);
						$white = imagecolorallocate($image_p, $colorR, $colorG, $colorB);
						imagefill($image_p, 0, 0, $white);
						switch($filetype){
							case "gif":
								$image = @imagecreatefromgif($file->getPathName());
							break;
							case "jpg":
								$image = @imagecreatefromjpeg($file->getPathName());
							break;
							case "jpeg":
								$image = @imagecreatefromjpeg($file->getPathName());
							break;
							case "png":
								$image = @imagecreatefrompng($file->getPathName());
							break;
						}
						@imagecopyresampled($image_p, $image, 0, $top_offset, 0, 0, $fwidth, $fheight, $width_orig, $height_orig);
						switch($filetype){
							case "gif":
								if(!@imagegif($image_p, $save)){
									$errorList[]= "PERMISSION DENIED [GIF]";
								}
							break;
							case "jpg":
								if(!@imagejpeg($image_p, $save, 100)){
									$errorList[]= "PERMISSION DENIED [JPG]";
								}
							break;
							case "jpeg":
								if(!@imagejpeg($image_p, $save, 100)){
									$errorList[]= "PERMISSION DENIED [JPEG]";
								}
							break;
							case "png":
								if(!@imagepng($image_p, $save, 0)){
									$errorList[]= "PERMISSION DENIED [PNG]";
								}
							break;
						}
						@imagedestroy($filename);
					}else{
						$errorList[]= "CANNOT MAKE IMAGE IT ALREADY EXISTS";
					}	
				}
			}		
		}else{
			$errorList[]= "NO FILE SELECTED";
		}
		if(!$match){
		   	$errorList[]= "File type isn't allowed: $filename";
		}
		if(sizeof($errorList) == 0 && isset($errorList)){
			return $fullPath.$newfilename;
		}else{
			$eMessage = array();
			for ($x=0; $x<sizeof($errorList); $x++){
				$eMessage[] = $errorList[$x];
			}
		   	return $eMessage;
		}
	}

    public function deleteLogoAction($lien, $png)
    {
        if ($this->testNameValide($png))
        {
            if (!($this->testFileExists('../deleted/marques', $png)))
            {
                $this->moveImage('marques/', '..deleted/marques/', $png);
            }else
            {
                $this->moveImage('marques/', '..deleted/marques/'.time().'_', $png);
            }
            return new Response();
        }
    }
    
    private function testFileExists($dossier, $file)
    {
        if (exec('ls '.$dossier.' | grep '.$file) == $file)
        {
            return true;
        }else
        {
            return false;
        }
    }

    private function testNameValide($name)
    {
        if (preg_match('/([a-zA-Z0-9]+\-([a-zA-Z0-9]+|)|[a-zA-Z0-9]+)\.(png|jpg|jpeg)/', $name) == 1)
        {
            return true;
        }else
        {
            return false;
        }
    }

    public function saveSliderAction($active, $inactive)
    {
        foreach ($active as $file)
        {
            if ($this->testNameValide($file))
            {
                if (!($this->testFileExists('./bundles/euroliteriestructure/images/slider/active/', $file))
                    && $this->testFileExists('./bundles/euroliteriestructure/images/slider/inactive/', $file))
                {
                    $this->moveImage('slider/inactive/', 'slider/active/', $file);
                }
            }
        }
        foreach($inactive as $file)
        {
            if ($this->testNameValide($file))
            {
                if (!($this->testFileExists('./bundles/euroliteriestructure/images/slider/inactive/', $file))
                    && $this->testFileExists('./bundles/euroliteriestructure/images/slider/active/', $file))
                {
                    $this->moveImage('slider/active/', 'slider/inactive/', $file);
                }
            }
        }
        return new Response();
    }

    private function moveImage($dossier1, $dossier2, $img)
    {
        exec('mv ./bundles/euroliteriestructure/images/'.$dossier1.$img.' ./bundles/euroliteriestructure/images/'.$dossier2.$img); 
    }
}
