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
use App\Repository\ServerRepository;
use App\Service\PermissionManager;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    private const int MAX_NAME_LENGTH = 27;
    private const int MAX_PSEUDO_LENGTH = 25;
    private const array IMAGE_MIME = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

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
    public function showServer(Request $request, PermissionManager $permissionManager, MessageRepository $messageRepository, ServerRepository $serverRepository, int $id, int $serverId = -1)
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
            return new JsonResponse(["error" => "Accès refusé à ce serveur"], 403);

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

        return new JsonResponse([
            "serverName" => $server->getName(),
            "serverId" => $server->getId(),
            "serverIcon" => $server->getIcon(),
            "roles" => $server->getRoles(),
            "channels" => $channelsJSON,
            "currentChannel" => $channelsJSON[$channelIndex]["id"],
            "messages" => $messages,
            "members" => $serverRepository->getUsersInfo($id)
        ]);
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

            return new JsonResponse($servers);
        } else {
            return new JsonResponse(["error" => "Requête invalide"], 400);
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
            return new JsonResponse(["error" => "Token invalide"], 403);
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

            return new JsonResponse(["status" => "ok"], 200);
        } else {
            // Renvoie un acces denied
            return new JsonResponse(["error" => "Données du serveur invalides"], 400);
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
            return new JsonResponse(["error" => "Token invalide"], 403);
        }
        // retrieves an instance of UploadedFile identified by "attachment"
        $file = $request->files->get('attachment');

        if ($file) {
            extract(self::handleFileUpload($attachmentDir, $file, 50, $slugger, self::IMAGE_MIME));

            if($status[0] != 200){
                return new JsonResponse(["error" => $status[1]], $status[0]);
            }

            return new JsonResponse(["fileName" => $newFilename], 200);
        } else {
            return new JsonResponse(["error" => "Aucun fichier"], 400);
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
        if(!$server){
            return new JsonResponse(["error" => "Serveur introuvable"], 400);
        }
        $roles = $server->getRoles();

        // vérifie en meme temps si l'utilisateur a acces au serveur et si il a la permission de créer une invitation
        /** @var User $user */
        $user = $this->getUser();
        if (!$permissionManager->hasServerRight($user->getId(), $roles, PermissionEnum::CreateInvit)) {
            return new JsonResponse(["error" => "Permission insuffisante"], 403);
        }
        ;

        //génère un identifiant unique 
        do {
            $random = bin2hex(random_bytes(4));
            $result = $sir->findOneBy(["identifiant" => $random]);
        } while ($result != null);

        //récupère les invitations existantes 
        $invitations = $serverInvitationRepository->findBy(["server" => $server]);

        $data = [];
        foreach ($invitations as $invitation) {
            // supprime les invit expirées
            $expirationDate = $invitation->getExpirationDate();
            if($expirationDate && $expirationDate < new DateTime("now")){
                $entityManager->remove($invitation);
                $entityManager->flush();
                continue;
            }

            $data[] = [
                "id" => $invitation->getId(),
                "identifiant" => $invitation->getIdentifiant(),
                "expirationDate" => $invitation->getExpirationDate()?->getTimestamp() * 1000,
            ];
        }

        return new JsonResponse(["randomId" => $random, "invitations" => $data], 200);
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
            return new JsonResponse(["error" => $status[1]], $status[0]);
        }

        // TODO: bloquer le nombre d'invit par serveur à 10

        $identifiant = trim($data->get('newInvit'));

        if(empty($identifiant)){
            return new JsonResponse(["error" => "Identifiant vide"], 400);
        }

        if(mb_strlen($identifiant) > 77){
            return new JsonResponse(["error" => "Identifiant trop long"], 400);
        }

        if ($sir->findOneBy(["identifiant" => $identifiant]))
            return new JsonResponse(["error" => "Identifiant déjà utilisé"], 400);

        $invit = new ServerInvitation();
        $invit->setIdentifiant($identifiant);
        $invit->setServer($server);

        if(empty($data->get('expirationDate'))){
            return new JsonResponse(["error" => "Insérer une date d'expiration"], 400);
        }
        $ms = (int) $data->get('expirationDate');
        $expirationDate = (new DateTime())->setTimestamp(intdiv($ms, 1000));
        $invit->setExpirationDate($expirationDate);

        $entityManager->persist($invit);
        $entityManager->flush();

        return new JsonResponse(["status" => "ok"], 200);
    }

    #[Route('/app/joinServer', name: 'app_joinServer', methods: ["POST"])]
    public function joinServer(
        ServerInvitationRepository $sir,
        Request $request,
        EntityManagerInterface $entityManager,
    ) {
        $data = $request->getPayload();
        if (!$this->isCsrfTokenValid(self::CRSF_ID, $data->get('token')))
            return new JsonResponse(["error" => "Token invalide"], 403);

        $link = trim($data->get("serverLink"));

        // l'identifiant est la portion entre "/join/" et le "/" suivant (ou la fin de la chaîne)
        if (!preg_match('#/join/([^/]+)#', $link, $matches)) {
            return new JsonResponse(["error" => "Lien d'invitation invalide"], 400);
        }
        $identifiant = $matches[1];

        $invitation = $sir->findOneBy(["identifiant" => $identifiant]);
        if (!$invitation)
            return new JsonResponse(["error" => "Invitation invalide"], 403);

        $expirationDate = $invitation->getExpirationDate();
        if($expirationDate && $expirationDate < new DateTime("now")){
            $entityManager->remove($invitation);
            $entityManager->flush();
            return new JsonResponse(["error" => "Invitation expirée"], 400);
        }

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
            return new JsonResponse(["error" => $status[1]], $status[0]);
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

            extract(self::handleFileUpload($serverIcon, $file, 8, $slugger, self::IMAGE_MIME));

            if($status[0] != 200){
                return new JsonResponse(["error" => $status[1]], $status[0]);
            }

            $server->setIcon($newFilename);
        }

        $newName = trim($data->get("editName"));
        if (!empty($newName)) {
            if(mb_strlen($newName) <= self::MAX_NAME_LENGTH){
                $server->setName($newName);
            } else {
                return new JsonResponse(["error" => "Nom de serveur trop long"], 400);
            }
        }

        $entityManager->flush();

        self::updateUsers($websocketNotifier, $server);

        return new JsonResponse(["status" => "ok"], 200);
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
            return new JsonResponse(["error" => $status[1]], $status[0]);
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

        return new JsonResponse(["status" => "ok"], 200);
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
            return new JsonResponse(["error" => $status[1]], $status[0]);
        }

        $channel = new Channel();
        $newName = trim($data->get("channelName"));
        if (!empty($newName)) {
            if(mb_strlen($newName) <= self::MAX_NAME_LENGTH){
                $channel->setName($newName);
            } else {
                return new JsonResponse(["error" => "Nom de canal trop long"], 400);
            }
        } else {
            return new JsonResponse(["error" => "Le nom du canal ne peut pas être vide"], 400);
        }

        $channel->setServer($server);

        $category = trim($data->get("category"));
        if (!empty($category)) {
            if(mb_strlen($category) <= self::MAX_NAME_LENGTH){
                $channel->setCategory($category);
            } else {
                return new JsonResponse(["error" => "Nom de catégorie trop long"], 400);
            }
        }

        // todo: remplacer les défauts
        $channel->setType(ChannelTypeEnum::Textual);

        $entityManager->persist($channel);
        $entityManager->flush();

        self::updateUsers($websocketNotifier, $server);

        return new JsonResponse(["status" => "ok"], 200);
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
            return new JsonResponse(["error" => $status[1]], $status[0]);
        }

        $channel = $entityManager->getRepository(Channel::class)->find($data->get("channelId"));

        // le canal doit appartenir au serveur validé (sinon on éditerait le canal d'un autre serveur)                                           
        if (!$channel || $channel->getServer()->getId() != $server->getId()) {
            return new JsonResponse(["error" => "Bad request"], 403);
        }
        
        $newName = trim($data->get("editedChannelName"));
        if ($newName && $newName != "") {
            if(mb_strlen($newName) <= self::MAX_NAME_LENGTH){
                $channel->setName($newName);
            } else {
                return new JsonResponse(["error" => "Nom de canal trop long"], 400);
            }
        }

        // catégorie vide => null (canal sans catégorie)                                                                                         
        $category = trim($data->get("category") ?? "");
        if ($category === "") {
            $category = null;
        } elseif (mb_strlen($category) > self::MAX_NAME_LENGTH) {
            return new JsonResponse(["error" => "Nom de catégorie trop long"], 400);
        }
        $channel->setCategory($category);

        $entityManager->flush();

        self::updateUsers($websocketNotifier, $server);

        return new JsonResponse(["status" => "ok"], 200);
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
            return new JsonResponse(["error" => $status[1]], $status[0]);
        }

        $channel = $entityManager->getRepository(Channel::class)->find($data->get("channelId"));

        // le canal doit appartenir au serveur validé (sinon on éditerait le canal d'un autre serveur)                                           
        if (!$channel || $channel->getServer()->getId() != $server->getId()) {
            return new JsonResponse(["error" => "Bad request"], 403);
        }

        self::deleteAllChannelAttachment($data->get("channelId"), $entityManager, $attachmentDir);

        self::updateUsers($websocketNotifier, $server);

        $entityManager->remove($channel);
        $entityManager->flush();

        return new JsonResponse(["status" => "ok"], 200);
    }



    #[Route('/app/editUser', name: 'app_editUser', methods: ["POST"])]
    public function editUser(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/public/uploads/pdp')] string $pdpDirectory
    ) {
        if (!$this->isCsrfTokenValid(self::CRSF_ID, $request->getPayload()->get('token'))) {
            return new JsonResponse(["error" => "Token invalide"], 403);
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

            //$newFilename, $status
            extract(self::handleFileUpload($pdpDirectory, $file, 8, $slugger, self::IMAGE_MIME));

            if($status[0] != 200){
                return new JsonResponse(["error" => $status[1]], $status[0]);
            }

            $user->setAvatarUrl($newFilename);
        }

        $newName = trim($data->get("editPseudo"));
        if (!empty($newName)) {
            if(mb_strlen($newName) <= self::MAX_PSEUDO_LENGTH){
                $user->setPseudo($newName);
            } else {
                return new JsonResponse(["error" => "Pseudo trop long"], 400);
            }
        }

        $newHandle = trim($data->get("editHandle"));
        if (!empty($newHandle)) {
            if(mb_strlen($newHandle) <= self::MAX_NAME_LENGTH){
                $user->setHandle($newHandle);
            } else {
                return new JsonResponse(["error" => "Handle trop long"], 400);
            }
        }

        $entityManager->flush();

        return new JsonResponse(["status" => "ok"], 200);
    }

    #[Route('/app/deleteUser', name: 'app_deleteUser', methods: ["POST"])]
    public function deleteUser(
        Request $request,
        EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/public/uploads/pdp')] string $pdpDirectory
    ) {
        // TODO: supprimer les serveurs orphelins et passer le role admin a un membre
        if (!$this->isCsrfTokenValid(self::CRSF_ID, $request->getPayload()->get('token'))) {
            return new JsonResponse(["error" => "Token invalide"], 403);
        }

        /** @var User $user */
        $user = $this->getUser();

        $avatarUrl = $pdpDirectory . "/" . $user->getAvatarUrl();
        if (is_file($avatarUrl)) {
            if (!unlink($avatarUrl)) {
            }
        }

        // Doctrine remove tout ce qui est associé en cascade
        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(["status" => "ok"], 200);
    }

    #[Route('/app/search', name: 'app_search', methods: ["POST"])]
    public function search(
        Request $request,
        EntityManagerInterface $entityManager,
        PermissionManager $permissionManager,
        MessageRepository $messageRepository
    ) {
        // $status, $data, $server, $user, $channel
        extract(self::accessChannel($request, $entityManager, $permissionManager, PermissionEnum::Read));

        if ($status[0] != 200) {
            return new JsonResponse(["error" => $status[1]], $status[0]);
        }

        $query = trim($data->get("query"));

        if($query == ""){
            return new JsonResponse(["error" => "Requête vide"], 404);
        }

        $result = $messageRepository->search($query, $data->get("channelId"));

        return new JsonResponse(["result" => $result], 200);
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
                };
            }
        }

        return [
            "status" => $status,
            "data" => $data,
            "server" => $server,
            "user" => $user
        ];
    }

    private function accessChannel(Request $request, EntityManagerInterface $entityManager, PermissionManager $permissionManager, PermissionEnum $permission)
    {
        $status = [200, "All Good"];
        $data = null;
        $server = null;
        $user = null;
        $channel = null;

        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid(self::CRSF_ID, $request->getPayload()->get('token'))) {
            $status = [403, "Token invalide"];
        } else {
            $data = $request->getPayload();
            
            $result = $entityManager->getRepository(User::class)->findWithChannelAccess($user->getId(), $data->get('channelId'));
            if(!$result){
                $status = [403, "Accès non autorisé"];
            } else {
                $server = $result["server"];
                $roles = $result["roles"];
                $channel = $result["channel"];
                
                if (!$permissionManager->canAccessChannel($data->get("channelId"), $user->getId(), $roles, $permission)) {
                    $status = [403, "Permission manquante"];
                }
            }
        }

        return [
            "status" => $status,
            "data" => $data,
            "server" => $server,
            "user" => $user,
            "channel" => $channel
        ];
    }

    private function handleFileUpload($directory, UploadedFile $file, int $maxMoSize, SluggerInterface $slugger, $acceptedMimes): array{
            $status = [200, "All Good"];
            $newFilename = "";
            if($file->getSize() > $maxMoSize * 1000000){
                $status = [400, "Fichier trop large"];
            } else {
                if ($acceptedMimes && !in_array($file->getMimeType(), $acceptedMimes, true)) {
                    $status = [400, "Type de fichier non autorisé"];
                } else {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
                    try {
                        $file->move($directory, $newFilename);
                    } catch (FileException $e) {
                        $status = [400, "Erreur lors de l'envoi du fichier"];
                    }
                }
            };

            return ["newFilename" => $newFilename, "status" => $status];
    }

}
