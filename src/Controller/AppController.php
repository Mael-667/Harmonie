<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\Server;
use App\Entity\ServerInvitation;
use App\Entity\User;
use App\Enum\ChannelTypeEnum;
use App\Enum\PermissionEnum;
use App\Form\ServerType;
use App\Repository\MessageRepository;
use App\Repository\ServerInvitationRepository;
use App\Service\PermissionManager;
use DateTime;
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
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;



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
                "newServerForm" => $newServerForm->createView(),
                // TODO: générer l'id aléatoirement
                "newId" => "efi6hu"
            ]);

        } else {
            throw new HttpException(400, 'Bad request');
        }
    }

    // GET /articles/42 — détail, id entier uniquement
    #[Route('/app/{id}/{serverId}', name: 'app_showServer', requirements: ['id' => '\d+'])]
    public function showServer(Request $request, PermissionManager $permissionManager, MessageRepository $messageRepository, int $id, int $serverId = -1){
        // Descriminer une premiere connexion d'une requete ajax avec le format de requete
        // La partie JS se chargera de request les données si elles sont demandées dans l'url
        if ($request->getPreferredFormat() != 'json'){
            return $this->index();
        }
        
        // Pour vérifier si l'utilisateur a bien acces au serveur demandé,
        // Je récupère tous les serveurs de l'utilisateur puis je filtre avec l'id du serveur demandé,
        // Si l'utilisateur a bien acces au serveur il devrait y avoir un match, sinon acces denied 
        
        /** @var User $user */
        $user = $this->getUser();

        /** @var Server $server */
        $server = $user->getServers()->filter(function ($e) use ($id) {
            return $e->getId() == $id;
        })->first();

        if(!$server) throw $this->createAccessDeniedException();

        $channels = $server->getChannels()->toArray();

        // Par défaut on affiche le 1er channel du serveur
        $channelIndex = 0;

        $channelsJSON = [];
        for ($i=0; $i < count($channels); $i++) { 
            $channelsJSON[$i] = [
                "id" => $channels[$i]->getId(),
                "type" => $channels[$i]->getType()->value,
                "name" => $channels[$i]->getName(),
                "category" => $channels[$i]->getCategory()
            ];

            if ($channelsJSON[$i]["id"] == $serverId) $channelIndex = $i;
        }

        if($permissionManager->canAccessChannel($channelsJSON[$channelIndex]["id"], $user->getId(), $server->getRoles())){
            $messages = $messageRepository->getLastMessages($channels[$channelIndex]);
        } else {
            $messages = ["error" => "access denied"];
        };
        
        return new Response(json_encode([
            "serverName" => $server->getName(),
            "serverId" => $server->getId(),
            "roles" => $server->getRoles(),
            "channels" => $channelsJSON,
            "currentChannel" => $channelsJSON[$channelIndex]["id"],
            "messages" => $messages
        ]));
    }

    #[Route('/app/getServers', name: 'app_getServers', methods: ["GET"])]
    public function getServers(){
        $user = $this->getUser();
        if($user instanceof User){
            $package = new Package(new EmptyVersionStrategy());
            $serversObject = $user->getServers()->toArray();
            // dd($serversObject);

            $servers = [];

            for ($i=0; $i < count($serversObject); $i++) { 
                $servers[$i] = [
                    "id" => $serversObject[$i]->getId(),
                    "name" => $serversObject[$i]->getName(),
                    "icon" => $package->getUrl("uploads/serverIcon/".$serversObject[$i]->getIcon()),
                ];
            }

            return new Response(json_encode($servers));
        } else {
            throw new HttpException(400, 'Bad request');
        }
    }


    #[Route('/app/newServer', name: 'app_newServer', methods: ["POST"])]
    public function newServer(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager,
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

                // Move the file to the directory where img are stored
                try {
                    $icon->move($serverIcon, $newFilename);
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                // updates the 'brochureFilename' property to store the PDF file name
                // instead of its contents
                $server->setIcon($newFilename);
            }

            /** @var User $admin */
            $admin = $this->getUser();
            $server->addUser($admin);
            
            
            $defaultChannel = new Channel();
            $defaultChannel->setType(ChannelTypeEnum::Textual);
            $defaultChannel->setName("Général");
            $server->addChannel($defaultChannel);

            // On persiste d'abord pour que le channel obtienne son id en base,
            // sinon $defaultChannel->getId() vaut null.
            $entityManager->persist($server);
            $entityManager->persist($defaultChannel);
            $entityManager->flush();

            $permissionManager->addAdminPermission($server, $admin->getId());
            $permissionManager->addDefaultPermission($server, $defaultChannel->getId());

            // On sauvegarde les rôles mis à jour sur le serveur.
            $entityManager->flush();

            return new Response(json_encode(["message" => "ALL IS GOOD"]));
        } else {
            // Renvoie un acces denied
            throw $this->createAccessDeniedException();
        }
    }

    #[Route('/app/fileUpload', name: 'app_fileUpload', methods: ["POST"])]
    public function fileUpload(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager,
        #[Autowire('%kernel.project_dir%/public/uploads/attachments')] string $attachmentDir
    ){
        // retrieves an instance of UploadedFile identified by "attachment"
        $file = $request->files->get('attachment');

        if ($file) {
            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
            try {
                $file->move($attachmentDir, $newFilename);
            } catch (FileException $e) {
                // ... handle exception if something happens during file upload
            }

            return new Response(json_encode(["fileName" => $newFilename]), 200);
        } else {
            return new Response(json_encode(["error" => "Aucun fichier"]), 400);
        }
    }


    #[Route('/app/getInvitId', name: 'app_getInvitId', methods: ["GET"])]
    public function getUniqueInvitId(ServerInvitationRepository $sir){
        do{
            $random = bin2hex(random_bytes(4));
            $result = $sir->findOneBy(["identifiant" => $random]);
        } while($result != null); 

        return new Response(json_encode(["randomId" => $random]), 200);
    }

    #[Route('/app/newInvit', name: 'app_newInvit', methods: ["POST"])]
    public function createNewInvit(ServerInvitationRepository $sir, Request $request, 
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager
    ){
        $data = $request->getPayload();
        if (!$this->isCsrfTokenValid('update-server', $data->get('token'))) return new Response('Token invalide', 403);


        // bloque ce chemin si l'utilisateur n'a pas le role créer invit
        $roles = $entityManager->getRepository(Server::class)->findOneBy(["id" => $data->get('serverId')])->getRoles();

        // vérifie en meme temps si l'utilisateur a acces au serveur et si il a la permission de créer une invitation
        /** @var User $user */
        $user = $this->getUser();
        if(!$permissionManager->hasServerRight($user->getId(), $roles, PermissionEnum::CreateInvit)){
             throw $this->createAccessDeniedException();
        };


        $identifiant = $data->get('newInvit');

        if($sir->findOneBy(["identifiant" => $identifiant])) return new Response('Identifiant déjà utilisé', 400);

        $invit = new ServerInvitation();
        $invit->setIdentifiant($identifiant);
        $invit->setServer($entityManager->getRepository(Server::class)->find($data->get('serverId')));

        $ms = (int) $data->get('expirationDate');
        $expirationDate = (new DateTime())->setTimestamp(intdiv($ms, 1000));
        $invit->setExpirationDate($expirationDate);

        $entityManager->persist($invit);
        $entityManager->flush();

        return new Response(json_encode(["error" => "Aucun pas d'error"]));
    }

    #[Route('/app/getAllInvitations', name: 'app_getAllInvitations', methods: ["POST"])]
    public function getAllInvitations(Request $request, EntityManagerInterface $entityManager, PermissionManager $permissionManager, ServerInvitationRepository $serverInvitationRepository){
        $data = $request->getPayload();

        // if (!$this->isCsrfTokenValid('get-all-invit', $data->get('token'))) return new Response('Token invalide', 403);

        // bloque ce chemin si l'utilisateur n'a pas le role créer invit
        $server = $entityManager->getRepository(Server::class)->findOneBy(["id" => $data->get('serverId')]);
        $roles = $server->getRoles();

        // vérifie en meme temps si l'utilisateur a acces au serveur et si il a la permission de créer une invitation
        /** @var User $user */
        $user = $this->getUser();
        if(!$permissionManager->hasServerRight($user->getId(), $roles, PermissionEnum::CreateInvit)){
             throw $this->createAccessDeniedException();
        };

        $invitations = $serverInvitationRepository->findBy(["server" => $server]);

        $data = [];
        foreach ($invitations as $invitation) {
            $data[] = [
                "id" => $invitation->getId(),
                "identifiant" => $invitation->getIdentifiant(),
                "expirationDate" => $invitation->getExpirationDate()?->getTimestamp() * 1000,
            ];
        }


        return new Response(json_encode($data));
    }
}
