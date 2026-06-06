import { useState, useEffect } from "react";
import Modal from "./modules/Modal";
import FormPost from "./modules/FormPost";

export default function ServerSettings({setSettingsOpened, serverId}) {
    // set la valeur par défaut
    const [tab, setTab] = useState("properties");
    const [invitUrl, setInvitUrl] = useState("");
    const [invitId, setInvitId] = useState("");
    const url = (window.location.origin).replace(window.location.protocol, "").replace("//", "");

    useEffect(() => {
        let form = new FormData();
        form.append("serverId", serverId);

        fetch(window.location.origin + "/app/setupInvit", {
            method: "POST",
            body: form,
        })
        .then((response) => response.json())
        .then((e) => {
            setInvitUrl(url + "/join/");
            setInvitId(e.randomId);
        });
    }, [serverId, url])

    function submitNewInvit(form) {
        form.append("serverId", serverId);
        form.append("expirationDate", (Date.now()) + 99999999999);

        fetch(window.location.origin + "/app/newInvit", {
            method: "POST",
            body: form
        })
        .then((response) => response.json())
        .then((e) => {
            console.log(e);
        })
        .catch((err) => {
            // indiquer a l'utilisateur que l'id est deja pris
            console.log(err);
        })
    }

    return <Modal id="popupServerSettings" onClose={() => setSettingsOpened(false)}>
            <h2>Administration du serveur</h2>
            <div id="settingsContent">
                <nav id="serverSettingsNav">
                    <button className="navButton" onClick={() => setTab('properties')}>Propriétés du serveur</button>
                    <button className="navButton" onClick={() => setTab('users')}>Gestion d'utilisateurs</button>
                    <button className="navButton" onClick={() => setTab('roles')}>Gestion des rôles</button>
                </nav>

                <div id="settingsDetails">

                    {tab === "properties" && (<div id="propertiesContent" className="settingsContent">
                        <div>
                            <h3>Créer une invitation</h3>
                            <FormPost className="singleLineForm" id="newInvitForm"
                                onSubmit={(e) => { e.preventDefault(); submitNewInvit(new FormData(e.currentTarget)); }}>
                                <div className="field">
                                    <label htmlFor="newInvit">Modifiez l'url selon vos besoins</label>
                                    <span id="invitUrlConteneur"><span id="invitUrl">{invitUrl}</span><input type="text" name="newInvit" id="newInvit" defaultValue={invitId}/></span>
                                </div>
                                <button type="submit" className="button crimsonButton">Créer et copier le lien</button>
                            </FormPost>
                        </div>
                        <div>
                            <h3>Liste des invitations</h3>
                        </div>
                    </div>)}

                    {tab === "users" && (<div id="usersContent">

                    </div>)}

                    {tab === "roles" && (<div id="rolesContent">

                    </div>)}
                </div>
            </div>
        </Modal>
}