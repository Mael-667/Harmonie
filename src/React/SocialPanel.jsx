// quand on join un channel, envoyer un message websocket pour signaler où nous sommes, avec le channelid et le serverid. Comme ça quand on check la liste des personnes connectés on a juste a filtrer par id

import { useContext, useEffect, useState } from "react";
import { WebsocketContext } from "./modules/WebsocketProvider";
import { FormPost } from "./modules/FormComponents";
import Message from "./Message";

export default function SocialPanel({ channelId, serverId, userList }) {

    const ws = useContext(WebsocketContext);

    // Liste des utilisateurs connectés sur le serveur courant
    const [connectedUsers, setConnectedUsers] = useState([]);
    const [searchResult, setSearchResult] = useState(null);

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

    function search(e){
        e.preventDefault();
        let form = new FormData(e.currentTarget);
        form.append("channelId", channelId);
        form.append("serverId", serverId);

        fetch(origin + "/app/search", {
            method: "POST",
            body: form,
        })
        .then((response) => response.json())
        .then((e) => {
            console.log(e);
            setSearchResult(e.result);
        })
        .catch((err) => console.log(err))
    }

    function resetResults(e){
        if(e.target.value == ""){
            setSearchResult(null)
        }
    }

    return <section id="social" aria-label="Membres et recherche">
        <div id="search">
            <FormPost className={"searchForm"} onSubmit={search}>
                <input type="text" name="query" id="query" className="searchInput" autoComplete="off" placeholder="Recherchez un message" aria-label="Rechercher un message" onChange={resetResults}/>
                <button type="submit" className="actionButton" aria-label="Rechercher">
                    <i className="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                </button>
            </FormPost>
        </div>
        {!searchResult ? (
            <div id="members" className="socialPanelContent">
                {userList.map((e, i) => <Member key={i} user={e} connectedUsers={connectedUsers} />)}
            </div>
        ) : (
            <div id="searchResults" className="socialPanelContent">
                {searchResult.length != 0 ? (
                    searchResult.map((e, i) => <Message key={i} message={e} firstOfAuthor={true} />)
                ) : (
                    <p>Pas de résultat</p>
                )}
            </div>
        )}
    </section>
}

function Member({ key, user, connectedUsers }) {
    const connected = connectedUsers.find(i => i == user.id);
    return <div key={key} className="member">
        <div className="memberPfp">
            <img src={`/uploads/pdp/${user.avatar_url}`} alt="Member avatar" />
            <div className="statusPastille" role="img" style={{backgroundColor: connected ? "#14a900" :"#5e5e5e"}} aria-label={connected ? "En ligne" : "Hors ligne"}></div>
        </div>
        <div id="userHandles">
            <p id="userPseudo">{user.pseudo}</p>
            {/* <p id="userHandle">@{user.handle}</p> */}
        </div>
    </div>
}