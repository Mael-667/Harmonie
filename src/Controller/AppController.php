<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Server;
use App\Entity\User;
use App\Enum\ChannelTypeEnum;
use App\Form\ServerType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\EntityManagerInterface;



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
    public function newServer(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/public/uploads/serverIcon')] string $serverIcon
    ){

        $server = new Server();
        $newServerForm = $this->createForm(ServerType::class, $server);
        $newServerForm->handleRequest($request);

        if ($newServerForm->isSubmitted() && $newServerForm->isValid()) {

            $icon = $newServerForm->get("icon")->getData();

            if ($icon) {
                $originalFilename = pathinfo($icon->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $icon->guessExtension();

                // Move the file to the directory where pfps are stored
                try {
                    $icon->move($serverIcon, $newFilename);
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                // updates the 'brochureFilename' property to store the PDF file name
                // instead of its contents
                $server->setIcon($newFilename);
            }

            
            $admin = $this->getUser();
            $server->addUser($admin);
            
            
            $defaultChannel = new Channel();
            $defaultChannel->setType(ChannelTypeEnum::Textual);
            $defaultChannel->setName("Général");
            // Todo: ajouter les roles qui ont acces au chan

            $entityManager->persist($server);
            $entityManager->persist($defaultChannel);
            $entityManager->flush();

            return new Response(json_encode(["message" => "ALL IS GOOD"]));
        }


        // Renvoie un acces denied
        throw $this->createAccessDeniedException();
    }
}
