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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use App\Service\WebsocketNotifier;



#[IsGranted("ROLE_USER")]
final class AppController extends AbstractController
{
    private const string CRSF_ID = "app";

    #[Route('/app', name: 'app')]
    public function index(): Response
    {
        return $this->render('app/index.html.twig');
    }

    #[Route('/app/me', name: 'app_me')]
    public function userInfo(CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userProfile = [
            "pseudo" => $user->getPseudo(),
            "handle" => $user->getHandle(),
            "avatar" => $user->getAvatarUrl(),
            "id" => $user->getId(),
            // Token CSRF unique réutilisé par React sur toutes les requêtes mutantes.
            // id 'app' = statefull (hors stateless_token_ids), validé via isCsrfTokenValid('app', ...).
            "csrfToken" => $csrfTokenManager->getToken(self::CRSF_ID)->getValue()
        ];
        return new JsonResponse($userProfile);
    }

    // GET /articles/42 — détail, id entier uniquement
    #[Route('/app/{id}/{serverId}', name: 'app_showServer', requirements: ['id' => '\d+'])]
    public function showServer(Request $request, PermissionManager $permissionManager, MessageRepository $messageRepository, int $id, int $serverId = -1)
    {
        // Descriminer une premiere connexion d'une requete ajax avec le format de requete
        // La partie JS se chargera de request les données si elles sont demandées dans l'url
        if ($request->getPreferredFormat() != 'json') {
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

        if (!$server)
            throw $this->createAccessDeniedException();

        $channels = $server->getChannels()->toArray();

        // Par défaut on affiche le 1er channel du serveur
        $channelIndex = 0;

        $channelsJSON = [];
        for ($i = 0; $i < count($channels); $i++) {
            $channelsJSON[$i] = [
                "id" => $channels[$i]->getId(),
                "type" => $channels[$i]->getType()->value,
                "name" => $channels[$i]->getName(),
                "category" => $channels[$i]->getCategory()
            ];

            if ($channelsJSON[$i]["id"] == $serverId)
                $channelIndex = $i;
        }

        if ($permissionManager->canAccessChannel($channelsJSON[$channelIndex]["id"], $user->getId(), $server->getRoles())) {
            $messages = $messageRepository->getLastMessages($channels[$channelIndex]);
        } else {
            $messages = ["error" => "access denied"];
        }
        ;

        return new Response(json_encode([
            "serverName" => $server->getName(),
            "serverId" => $server->getId(),
            "serverIcon" => $server->getIcon(),
            "roles" => $server->getRoles(),
            "channels" => $channelsJSON,
            "currentChannel" => $channelsJSON[$channelIndex]["id"],
            "messages" => $messages
        ]));
    }

    #[Route('/app/getServers', name: 'app_getServers', methods: ["GET"])]
    public function getServers()
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $package = new Package(new EmptyVersionStrategy());
            $serversObject = $user->getServers()->toArray();
            // dd($serversObject);

            $servers = [];

            for ($i = 0; $i < count($serversObject); $i++) {
                $servers[$i] = [
                    "id" => $serversObject[$i]->getId(),
                    "name" => $serversObject[$i]->getName(),
                    "icon" => $package->getUrl("uploads/serverIcon/" . $serversObject[$i]->getIcon()),
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
    ) {
        if (!$this->isCsrfTokenValid(self::CRSF_ID, $request->getPayload()->get('token'))) {
            return new Response('Token invalide', 403);
        }

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
    ) {
        if (!$this->isCsrfTokenValid(self::CRSF_ID, $request->getPayload()->get('token'))) {
            return new Response('Token invalide', 403);
        }
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


    #[Route('/app/setupInvit', name: 'app_setupInvit', methods: ["POST"])]
    public function getUniqueInvitId(
        ServerInvitationRepository $sir,
        Request $request,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager,
        ServerInvitationRepository $serverInvitationRepository
    ) {

        $data = $request->getPayload();

        // bloque ce chemin si l'utilisateur n'a pas le role créer invit
        $server = $entityManager->getRepository(Server::class)->findOneBy(["id" => $data->get('serverId')]);
        $roles = $server->getRoles();

        // vérifie en meme temps si l'utilisateur a acces au serveur et si il a la permission de créer une invitation
        /** @var User $user */
        $user = $this->getUser();
        if (!$permissionManager->hasServerRight($user->getId(), $roles, PermissionEnum::CreateInvit)) {
            throw $this->createAccessDeniedException();
        }
        ;

        //génère un identifiant unique 
        do {
            $random = bin2hex(random_bytes(4));
            $result = $sir->findOneBy(["identifiant" => $random]);
        } while ($result != null);

        //récupère les invitations existantes 
        $invitations = $serverInvitationRepository->findBy(["server" => $server]);

        // Todo: supprimer les invit expirées
        $data = [];
        foreach ($invitations as $invitation) {
            $data[] = [
                "id" => $invitation->getId(),
                "identifiant" => $invitation->getIdentifiant(),
                "expirationDate" => $invitation->getExpirationDate()?->getTimestamp() * 1000,
            ];
        }

        return new Response(json_encode(["randomId" => $random, "invitations" => $data]), 200);
    }

    #[Route('/app/newInvit', name: 'app_newInvit', methods: ["POST"])]
    public function createNewInvit(
        ServerInvitationRepository $sir,
        Request $request,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager
    ) {
        // $status, $data, $server, $user
        extract(self::validateRequest($request, $entityManager, $permissionManager, PermissionEnum::CreateInvit));

        if ($status[0] != 200) {
            return new Response($status[1], $status[0]);
        }

        // TODO: bloquer le nombre d'invit par serveur à 10

        $identifiant = $data->get('newInvit');

        if ($sir->findOneBy(["identifiant" => $identifiant]))
            return new Response('Identifiant déjà utilisé', 400);

        $invit = new ServerInvitation();
        $invit->setIdentifiant($identifiant);
        $invit->setServer($server);

        $ms = (int) $data->get('expirationDate');
        $expirationDate = (new DateTime())->setTimestamp(intdiv($ms, 1000));
        $invit->setExpirationDate($expirationDate);

        $entityManager->persist($invit);
        $entityManager->flush();

        return new Response(json_encode(["error" => "Aucun pas d'error"]));
    }

    #[Route('/app/joinServer', name: 'app_joinServer', methods: ["POST"])]
    public function joinServer(
        ServerInvitationRepository $sir,
        Request $request,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager
    ) {
        $data = $request->getPayload();
        if (!$this->isCsrfTokenValid(self::CRSF_ID, $data->get('token')))
            return new Response('Token invalide', 403);

        $link = trim($data->get("serverLink"));

        // l'identifiant est la portion entre "/join/" et le "/" suivant (ou la fin de la chaîne)
        if (!preg_match('#/join/([^/]+)#', $link, $matches)) {
            return new Response(json_encode(["error" => "Lien d'invitation invalide"]), 400);
        }
        $identifiant = $matches[1];

        $invitation = $sir->findOneBy(["identifiant" => $identifiant]);
        if (!$invitation)
            return new Response('Invitation invalide', 403);

        $server = $invitation->getServer();

        /** @var User $user */
        $user = $this->getUser();
        $user->addServer($server);

        $entityManager->flush();

        return new JsonResponse(["serverId" => $server->getId()], 200);
    }



    #[Route('/app/editServer', name: 'app_editServer', methods: ["POST"])]
    public function editServer(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager,
        WebsocketNotifier $websocketNotifier,
        #[Autowire('%kernel.project_dir%/public/uploads/serverIcon')] string $serverIcon
    ) {
        // $status, $data, $server, $user
        extract(self::validateRequest($request, $entityManager, $permissionManager, PermissionEnum::EditServer));

        if ($status[0] != 200) {
            return new Response($status[1], $status[0]);
        }

        // retrieves an instance of UploadedFile identified by "attachment"
        $file = $request->files->get('serverIcon');

        if ($file) {

            // si l'utilisateur envoie une nouvelle icone, on doit supprimer l'ancienne
            $serverIconUrl = $serverIcon . "/" . $server->getIcon();
            if (is_file($serverIconUrl)) {
                if (!unlink($serverIconUrl)) {
                }
            }

            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
            try {
                $file->move($serverIcon, $newFilename);
            } catch (FileException $e) {
                // ... handle exception if something happens during file upload
            }

            $server->setIcon($newFilename);
        }

        $newName = $data->get("editName");

        if ($newName) {
            $server->setName($newName);
        }

        $entityManager->flush();

        self::updateUsers($websocketNotifier, $server);

        return new JsonResponse(["error" => "prank"], 200);
    }


    #[Route('/app/deleteServer', name: 'app_deleteServer', methods: ["POST"])]
    public function deleteServer(
        Request $request,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager,
        WebsocketNotifier $websocketNotifier,
        #[Autowire('%kernel.project_dir%/public/uploads/serverIcon')] string $serverIcon,
        #[Autowire('%kernel.project_dir%/public/uploads/attachments')] string $attachmentDir
    ) {
        // $status, $data, $server, $user
        extract(self::validateRequest($request, $entityManager, $permissionManager, PermissionEnum::EditServer));

        if ($status[0] != 200) {
            return new Response($status[1], $status[0]);
        }

        $serverIconUrl = $serverIcon . "/" . $server->getIcon();
        if (is_file($serverIconUrl)) {
            if (!unlink($serverIconUrl)) {
                // TODO: handle échec de la suppression (droits, fichier verrouillé…)
            }
        }

        $channels = $server->getChannels();

        foreach ($channels as $channel) {
            self::deleteAllChannelAttachment($channel->getId(), $entityManager, $attachmentDir);
        }

        self::updateUsers($websocketNotifier, $server);

        $entityManager->remove($server);
        $entityManager->flush();

        return new JsonResponse(["error" => "prank"], 200);
    }

    #[Route('/app/createChannel', name: 'app_createChannel', methods: ["POST"])]
    public function createChannel(
        Request $request,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager,
        WebsocketNotifier $websocketNotifier,
    ) {
        // $status, $data, $server, $user
        extract(self::validateRequest($request, $entityManager, $permissionManager, PermissionEnum::EditServer));

        if ($status[0] != 200) {
            return new Response($status[1], $status[0]);
        }

        $channel = new Channel();
        $channel->setName($data->get("channelName"));
        $channel->setServer($server);

        $category = $data->get("category");
        if ($category)
            $channel->setCategory($category);

        // todo: remplacer les défauts
        $channel->setType(ChannelTypeEnum::Textual);

        $entityManager->persist($channel);
        $entityManager->flush();

        self::updateUsers($websocketNotifier, $server);

        return new JsonResponse(["error" => "prank"], 200);
    }

    #[Route('/app/editChannel', name: 'app_editChannel', methods: ["POST"])]
    public function editChannel(
        Request $request,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager,
        WebsocketNotifier $websocketNotifier,
    ) {
        // champs reçus : token, serverId, channelId, editedChannelName, category

        // $status, $data, $server, $user
        extract(self::validateRequest($request, $entityManager, $permissionManager, PermissionEnum::EditServer));

        if ($status[0] != 200) {
            return new Response($status[1], $status[0]);
        }

        $channel = $entityManager->getRepository(Channel::class)->find($data->get("channelId"));

        // le canal doit appartenir au serveur validé (sinon on éditerait le canal d'un autre serveur)                                           
        if (!$channel || $channel->getServer()->getId() != $server->getId()) {
            return new Response("Bad request", 403);
        }

        $channel->setName($data->get("editedChannelName"));

        // catégorie vide => null (canal sans catégorie)                                                                                         
        $category = $data->get("category");
        if ($category === "")
            $category = null;
        $channel->setCategory($category);

        $entityManager->flush();

        self::updateUsers($websocketNotifier, $server);

        return new JsonResponse(["error" => "prank"], 200);
    }

    #[Route('/app/deleteChannel', name: 'app_deleteChannel', methods: ["POST"])]
    public function deleteChannel(
        Request $request,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager,
        WebsocketNotifier $websocketNotifier,
        #[Autowire('%kernel.project_dir%/public/uploads/attachments')] string $attachmentDir
    ) {
        // champs reçus : token, serverId, channelId

        // $status, $data, $server, $user
        extract(self::validateRequest($request, $entityManager, $permissionManager, PermissionEnum::EditServer));

        if ($status[0] != 200) {
            return new Response($status[1], $status[0]);
        }

        $channel = $entityManager->getRepository(Channel::class)->find($data->get("channelId"));

        // le canal doit appartenir au serveur validé (sinon on éditerait le canal d'un autre serveur)                                           
        if (!$channel || $channel->getServer()->getId() != $server->getId()) {
            return new Response("Bad request", 403);
        }

        self::deleteAllChannelAttachment($data->get("channelId"), $entityManager, $attachmentDir);

        self::updateUsers($websocketNotifier, $server);

        $entityManager->remove($channel);
        $entityManager->flush();

        return new JsonResponse(["error" => "prank"], 200);
    }



    #[Route('/app/editUser', name: 'app_editUser', methods: ["POST"])]
    public function editUser(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/public/uploads/pdp')] string $pdpDirectory
    ) {
        if (!$this->isCsrfTokenValid(self::CRSF_ID, $request->getPayload()->get('token'))) {
            return new Response('Token invalide', 403);
        }

        /** @var User $user */
        $user = $this->getUser();

        $data = $request->getPayload();
        $file = $request->files->get('avatar');

        // si l'utilisateur envoie une nouvelle icone, on doit supprimer l'ancienne
        if ($file) {
            $avatarUrl = $pdpDirectory . "/" . $user->getAvatarUrl();
            if (is_file($avatarUrl)) {
                if (!unlink($avatarUrl)) {
                }
            }

            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
            try {
                $file->move($pdpDirectory, $newFilename);
            } catch (FileException $e) {
                // ... handle exception if something happens during file upload
            }

            $user->setAvatarUrl($newFilename);
        }

        $newName = $data->get("editPseudo");
        if ($newName) {
            $user->setPseudo($newName);
        }

        $newHandle = $data->get("editHandle");
        if ($newHandle) {
            $user->setHandle($newHandle);
        }

        $entityManager->flush();

        return new JsonResponse(["error" => "prank"], 200);
    }

    #[Route('/app/deleteUser', name: 'app_deleteUser', methods: ["POST"])]
    public function deleteUser(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
    ) {
        if (!$this->isCsrfTokenValid(self::CRSF_ID, $request->getPayload()->get('token'))) {
            return new Response('Token invalide', 403);
        }

        /** @var User $user */
        $user = $this->getUser();

        $entityManager->remove($user);
        $entityManager->flush();
        
        return new JsonResponse(["error" => "prank"], 200);
    }


    private function deleteAllChannelAttachment($channelId, EntityManagerInterface $entityManager, $attachmentDir)
    {
        $attachments = $entityManager->getRepository(Channel::class)->getAllAttachments($channelId);

        foreach ($attachments as $attachment) {
            $attachmentUrl = $attachmentDir . "/" . $attachment["attachment"];
            if (is_file($attachmentUrl)) {
                if (!unlink($attachmentUrl)) {
                }
            }
        }
    }


    private function updateUsers(WebsocketNotifier $websocketNotifier, Server $server, string $message = "updateServer")
    {
        $recipientIds = [];
        $serverUsers = $server->getUsers();
        foreach ($serverUsers as $member) {
            $recipientIds[] = $member->getId();
        }

        $websocketNotifier->broadcast($message, $recipientIds);
    }

    private function validateRequest(Request $request, EntityManagerInterface $entityManager, PermissionManager $permissionManager, PermissionEnum $permission)
    {
        $status = [200, "All Good"];
        $data = null;
        $server = null;
        $user = null;

        if (!$this->isCsrfTokenValid(self::CRSF_ID, $request->getPayload()->get('token'))) {
            $status = [403, "Token invalide"];
        } else {
            $data = $request->getPayload();

            $server = $entityManager->getRepository(Server::class)->findOneBy(["id" => $data->get('serverId')]);

            if (!$server) {
                $status = [403, "Bad request"];
            } else {
                $roles = $server->getRoles();

                /** @var User $user */
                $user = $this->getUser();
                if (!$permissionManager->hasServerRight($user->getId(), $roles, $permission)) {
                    $status = [403, "Permission manquante"];
                }
                ;
            }
        }

        return [
            "status" => $status,
            "data" => $data,
            "server" => $server,
            "user" => $user
        ];
    }

}
