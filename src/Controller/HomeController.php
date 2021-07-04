<?php

namespace App\Controller;

use DateTime;
use FFMpeg\FFMpeg;
use App\Entity\User;
use App\Entity\Video;
use FFMpeg\Format\Video\WebM;
use FFMpeg\Format\Video\X264;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index(VideoRepository $videoRepository,UserRepository $userRepository): Response
    {
        $user = $userRepository->findOneBy(['ip' => $_SERVER['REMOTE_ADDR']]);
        $videos = $videoRepository->findBy(['user' => $user]);


        return $this->render('home/index.html.twig', [
            'videos' => $videos,
        ]);
    }

    

    /**
     * @Route("/uploadfile", name="upload_file", methods={"POST"})
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function UploadFile(Request $request,UserRepository $userRepository):JsonResponse
    {
        $valid_extensions = array("m4v","avi","mpg","mp4","mov","webm");

        if ($request->getMethod() == 'POST')
        {
            $entityManager = $this->getDoctrine()->getManager();
            $file = $request->files->get('attachement');
            $fileExtension = $file->guessClientExtension();

        }
        else {
            die();
        }
        
        $user = $userRepository->findOneBy(['ip'=>$_SERVER['REMOTE_ADDR']]);

        if($user == null){
            $user = new User();
            $user->setIp($_SERVER['REMOTE_ADDR']);
            $entityManager->persist($user);
            $entityManager->flush();
        }

        if($file){
            if(in_array(strtolower($fileExtension), $valid_extensions)) {

                
                $fileCode = alphaIDGenerator(rand(1000000, 99999999999));;
                $filename = $fileCode. '.' . $file->guessClientExtension();
                
                $temp_file = $file->move($this->getParameter('uploads_dir'),$filename);
                
                $videoUpload = new Video();
                $videoUpload->setUser($user);
                $videoUpload->setCode($fileCode);
                $videoUpload->setType('.mp4');
                $videoUpload->setName('test');
                $videoUpload->setDate(new \DateTime());

                $ffmpeg = FFMpeg::create();
                $video = $ffmpeg->open($temp_file);
                $video->filters()->resize(new Dimension(1280, 960))->synchronize();
                $video->frame(TimeCode::fromSeconds(1))->save($this->getParameter('uploads_dir').'/thumbnails/'.$fileCode.'.jpg');
                $video->save(new X264, $this->getParameter('uploads_dir').'/videos/'.$fileCode.'.mp4');

                $entityManager->persist($videoUpload);
                $entityManager->flush();

                unlink($temp_file);
            }  
            $response = 1;
        }else{
            $response = 0;
        }

        $returnResponse = new JsonResponse($response);
        $returnResponse->setjson($response);

        return $returnResponse;

    }

    



}

function alphaIDGenerator($in, $to_num = false, $pad_up = false, $pass_key = null)
    {
    $out   =   '';
    $index = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $base  = strlen($index);

    if ($pass_key !== null) {
        // Although this function's purpose is to just make the
        // ID short - and not so much secure,
        // with this patch by Simon Franz (https://blog.snaky.org/)
        // you can optionally supply a password to make it harder
        // to calculate the corresponding numeric ID

        for ($n = 0; $n < strlen($index); $n++) {
        $i[] = substr($index, $n, 1);
        }

        $pass_hash = hash('sha256',$pass_key);
        $pass_hash = (strlen($pass_hash) < strlen($index) ? hash('sha512', $pass_key) : $pass_hash);

        for ($n = 0; $n < strlen($index); $n++) {
        $p[] =  substr($pass_hash, $n, 1);
        }

        array_multisort($p, SORT_DESC, $i);
        $index = implode($i);
    }

    if ($to_num) {
        // Digital number  <<--  alphabet letter code
        $len = strlen($in) - 1;

        for ($t = $len; $t >= 0; $t--) {
        $bcp = bcpow($base, $len - $t);
        $out = $out + strpos($index, substr($in, $t, 1)) * $bcp;
        }

        if (is_numeric($pad_up)) {
        $pad_up--;

        if ($pad_up > 0) {
            $out -= pow($base, $pad_up);
        }
        }
    } else {
        // Digital number  -->>  alphabet letter code
        if (is_numeric($pad_up)) {
        $pad_up--;

        if ($pad_up > 0) {
            $in += pow($base, $pad_up);
        }
        }

        for ($t = ($in != 0 ? floor(log($in, $base)) : 0); $t >= 0; $t--) {
        $bcp = bcpow($base, $t);
        $a   = floor($in / $bcp) % $base;
        $out = $out . substr($index, $a, 1);
        $in  = $in - ($a * $bcp);
        }
    }

    return $out;
    }