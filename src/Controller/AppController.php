<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ServerType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_USER")]
final class AppController extends AbstractController
{
    #[Route('/app', name: 'app')]
    public function index(): Response
    {

        $user = $this->getUser();
        if($user instanceof User){

            $userProfile = [
                "pseudo" => $user->getPseudo(),
                "avatar" => $user->getAvatarUrl()
            ];

            $newServerForm = $this->createForm(ServerType::class);
    
            return $this->render('app/index.html.twig', [
                "user" => $userProfile,
                "newServerForm" => $newServerForm->createView()
            ]);

        } else {
            throw new HttpException(400, 'Bad request');
        }
    }

    #[Route('/app/newServer', name: 'app_newServer', methods: ["POST"])]
    public function newServer(){
        
    }
}
