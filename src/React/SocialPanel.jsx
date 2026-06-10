// quand on join un channel, envoyer un message websocket pour signaler où nous sommes, avec le channelid et le serverid. Comme ça quand on check la liste des personnes connectés on a juste a filtrer par id

import { useContext, useEffect, useState } from "react";
import { WebsocketContext } from "./modules/WebsocketProvider";

export default function SocialPanel({channelId, serverId}) {

    const ws = useContext(WebsocketContext);

    // Liste des utilisateurs connectés sur le serveur courant
    const [connectedUsers, setConnectedUsers] = useState([]);

    // Émission : signaler notre position + demander la liste initiale des connectés
    useEffect(() => {
        ws.send(JSON.stringify({ type: "currentLocation", channel: channelId, server: serverId }));
    }, [channelId, serverId, ws]);

    useEffect(() => {
        ws.send(JSON.stringify({ type: "getUsersStatus", server: serverId }));
    }, [serverId, ws])

    useEffect(() => {
        // Réponse à getUsersStatus → liste initiale
        ws.addListener("usersStatus", (payload) => {
            // payload = { connectedUsers: [...] }
            setConnectedUsers(payload.connectedUsers);
        });

        // Un utilisateur arrive / change de position sur le serveur courant
        ws.addListener("connectedUser", (payload) => {
            // payload = { userId }
            setConnectedUsers(m => [...m, payload.userId])
        });

        // Un utilisateur se déconnecte
        ws.addListener("disconnectedUser", (payload) => {
            // payload = { userId }
            setConnectedUsers(m => m.filter(userId => userId != payload.userId));
        });
    }, [ws]);

    return <div id="social">
        <div id="search">
            <form action="" method="get">
                <input type="text" name="query" id="query" />
                <button type="submit">
                    <i className="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>
        </div>
        <div id="users">

        </div>
    </div>
}