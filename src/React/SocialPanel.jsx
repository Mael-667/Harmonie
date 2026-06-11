// quand on join un channel, envoyer un message websocket pour signaler où nous sommes, avec le channelid et le serverid. Comme ça quand on check la liste des personnes connectés on a juste a filtrer par id

import { useContext, useEffect, useState } from "react";
import { WebsocketContext } from "./modules/WebsocketProvider";

export default function SocialPanel({ userList }) {

    const ws = useContext(WebsocketContext);

    // Liste des utilisateurs connectés sur le serveur courant
    const [connectedUsers, setConnectedUsers] = useState([]);

    useEffect(() => {
        const userIds = [];
        userList.forEach(user => {
            userIds.push(user.id)
        });

        ws.send(JSON.stringify({ type: "whichOnline", users: userIds }));
    }, [userList, ws]);

    useEffect(() => {
        // Réponse à getUsersStatus → liste initiale
        ws.addListener("usersStatus", (payload) => {
            // payload = { connectedUsers: [...] }
            setConnectedUsers(payload.connectedUsers);
        });

        // Un utilisateur arrive / change de position sur le serveur courant
        ws.addListener("connectedUser", (payload) => {
            setConnectedUsers(m => [...m, payload.userId])
        });

        // Un utilisateur se déconnecte
        ws.addListener("disconnectedUser", (payload) => {
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
        <div id="members">
            {userList.map((e, i) => <Member key={i} user={e} connectedUsers={connectedUsers} />)}
        </div>
    </div>
}

function Member({ key, user, connectedUsers }) {
    const connected = connectedUsers.find(i => i == user.id);
    return <div key={key} className="member">
        <div className="memberPfp">
            <img src={`/uploads/pdp/${user.avatar_url}`} alt="Member avatar" />
            <div className="statusPastille" style={{backgroundColor: connected ? "#14a900" :"#5e5e5e"}}></div>
        </div>
        <div id="userHandles">
            <p id="userPseudo">{user.pseudo}</p>
            <p id="userHandle">@{user.handle}</p>
        </div>
    </div>
}