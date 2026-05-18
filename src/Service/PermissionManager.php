<?php
namespace App\Service;

use App\Entity\Server;
use App\Enum\PermissionEnum;

class PermissionManager
{
    public function canAccessChannel($channelId, $userId, array $roles, PermissionEnum $permission = PermissionEnum::Read): bool
    {
        for($i = 0; $i < count($roles); ++$i){
            if(in_array($userId, $roles[$i]["members"]) || in_array("*", $roles[$i]["members"])){
                if(in_array("*", $roles[$i]["permissions"]) || (isset($roles[$i]["permissions"][$channelId]) && in_array($permission->value, $roles[$i]["permissions"][$channelId]))){
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
                "serverPermission" => [
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
                "serverPermission" => [
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
    //     "serverPermission" => [
    //         "*"
    //     ],
    //     "members" => [
    //         "*",
    //         "memberId"
    //     ]
    // ]
}