<?php
namespace App\Service;

use App\Entity\Server;

class PermissionManager
{
    public function canAccessChannel($channelId, $userId, array $roles, $permission = "read"): bool
    {
        for($i = 0; $i < count($roles); ++$i){
            if(in_array($userId, $roles[$i]["members"]) || in_array("*", $roles[$i]["members"])){
                if(in_array("*", $roles[$i]["permissions"]) || in_array($permission, $roles[$i]["permissions"][$channelId])){
                    return true;
                };
            }
        }

        return false;
    }

    public function addAdminPermission(Server $server, int $adminId){
        $currentRoles = $server->getRoles();
        $currentRoles[] = [
                "name" => "Admin",
                "color" => "#aa0000",
                "permissions" => [
                    "*"
                ],
                "members" => [
                    $adminId
                ]
            ];
        $server->setRoles($currentRoles);  
    }

    public function addDefaultPermission(Server $server, int $channelId){
        $currentRoles = $server->getRoles();
        $currentRoles[] =  [
                "name" => "User",
                "color" => "#ffffff",
                "permissions" => [
                    $channelId => [
                        "read",
                        "write"
                    ]
                ],
                "members" => [
                    "*"
                ]
            ];

        $server->setRoles($currentRoles);
    }



    // etoile = tout
    // $role = [
    //     "name" => "admin",
    //     "color" => "#0033aa"
    //     "permissions" => [
    //          "*",
    //          "chanId" => [
    //              "read",
    //              "write",
    //              "edit"
    //          ],
    //     ],
    //     "members" => [
    //         "*",
    //         "memberId"
    //     ]
    // ]
}