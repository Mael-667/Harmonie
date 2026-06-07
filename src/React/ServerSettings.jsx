import { useState, useEffect, useMemo } from "react";
import Modal from "./modules/Modal";
import { copy } from "./modules/Utils";
import { DynamicImageInput, Field, FormPost, Select } from "./modules/FormComponents";

export default function ServerSettings({ setSettingsOpened, server, channels }) {
    // set la valeur par défaut
    const [tab, setTab] = useState("properties");
    const [invitDetails, setInvitDetails] = useState(null);
    const url = (window.location.origin).replace(window.location.protocol, "").replace("//", "");

    const categories = useMemo(() => getCategories(channels) ,[channels])

    useEffect(() => {
        let form = new FormData();
        form.append("serverId", server.serverId);

        fetch(window.location.origin + "/app/setupInvit", {
            method: "POST",
            body: form,
        })
            .then((response) => response.json())
            .then((e) => {
                setInvitDetails({ randomId: e.randomId, invitations: e.invitations });
            });
    }, [server, url])

    function getCategories(channels){
        let categories = [];
        channels.forEach(c => {
            if (c.category && !categories.includes(c.category)) {
                categories.push(c.category);
            }
        })
        return categories;
    }

    function submitNewInvit(form) {
        form.append("serverId", server.serverId);
        form.append("expirationDate", (Date.now()) + 99999999999);

        fetch(window.location.origin + "/app/newInvit", {
            method: "POST",
            body: form
        })
            .then((response) => response.json())
            .then(() => {
                copy(`${url}/join/${form.get("newInvit")}`)
            })
            .catch((err) => {
                // indiquer a l'utilisateur que l'id est deja pris
                console.log(err);
            })
    }

    function editServer(e) {
        e.preventDefault();
        let form = new FormData(e.currentTarget);
        form.append("serverId", server.serverId)

        fetch(origin + "/app/editServer", {
            method: "POST",
            // Set the FormData instance as the request body
            body: form,
        })
        .then((response) => response.json())
        .then((e) => {
            console.log(e);
        })
        .catch((err) => console.log(err))
    }

    function deleteServer(e) {
        e.preventDefault();
        let form = new FormData(e.currentTarget);
        form.append("serverId", server.serverId)

        fetch(origin + "/app/deleteServer", {
            method: "POST",
            // Set the FormData instance as the request body
            body: form,
        })
        .then((response) => response.json())
        .then((e) => {
            console.log(e);
        })
        .catch((err) => console.log(err))
    }

    function createChannel(e) {
        e.preventDefault();
        let form = new FormData(e.currentTarget);
        form.append("serverId", server.serverId)

        fetch(origin + "/app/createChannel", {
            method: "POST",
            // Set the FormData instance as the request body
            body: form,
        })
        .then((response) => response.json())
        .then((e) => {
            console.log(e);
        })
        .catch((err) => console.log(err))
    }


    if (!invitDetails) return null;   // TODO: ou un <Modal>…Chargement…</Modal>

    return <Modal id="popupServerSettings" onClose={() => setSettingsOpened(false)}>
        <h2>Administration du serveur</h2>
        <div id="settingsContent">
            <nav id="serverSettingsNav">
                <button className={`navButton ${tab === "properties" ? "navButtonActive" : ""}`} onClick={() => setTab('properties')}>Propriétés du serveur</button>
                <button className={`navButton ${tab === "channels" ? "navButtonActive" : ""}`} onClick={() => setTab('channels')}>Canaux de discussion</button>
                <button className={`navButton ${tab === "users" ? "navButtonActive" : ""}`} onClick={() => setTab('users')}>Gestion d'utilisateurs</button>
                <button className={`navButton ${tab === "roles" ? "navButtonActive" : ""}`} onClick={() => setTab('roles')}>Gestion des rôles</button>
            </nav>

            <div id="settingsDetails">

                {tab === "properties" && (<div id="propertiesContent" className="settingsContent">
                    <div>
                        <h3>Modifier le serveur</h3>
                        <FormPost className={"servInfoForm"} onSubmit={editServer}>
                            <div className="servInfoEdit">
                                <DynamicImageInput name="serverIcon" id="serverIcon" tempPreview={`${origin}/uploads/serverIcon/${server.serverIcon}`}>
                                    <i className="fa-solid fa-pen imgInputIcon" aria-hidden="true"></i>
                                </DynamicImageInput>
                                <Field id={"editName"} label={"Renommez votre serveur"} defaultValue={server.serverName} />
                            </div>
                            <button type="submit" className="button crimsonButton" style={{ alignSelf: "end" }}>Mettre à jour</button>
                        </FormPost>
                    </div>
                    <div>
                        <h3>Créer une invitation</h3>
                        <FormPost className="singleLineForm" id="newInvitForm"
                            onSubmit={(e) => { e.preventDefault(); submitNewInvit(new FormData(e.currentTarget)); }}>
                            <div className="field">
                                <label htmlFor="newInvit">Modifiez l'url selon vos besoins</label>
                                <span id="invitUrlConteneur"><span id="invitUrl">{url + "/join/"}</span><input type="text" name="newInvit" id="newInvit" defaultValue={invitDetails.randomId} /></span>
                            </div>
                            <button type="submit" className="button crimsonButton">Créer et copier le lien</button>
                        </FormPost>
                    </div>
                    <div>
                        <h3>Liste des invitations</h3>
                        <span className="smallText secondaryColor">Cliquez pour copier !</span>
                        {invitDetails.invitations && (
                            <div className="listConteneur">
                                {invitDetails.invitations.map((e, i) =>
                                    <div key={i} className="activeInvitation" onClick={() => { copy(`${url}/join/${e.identifiant}`) }}>
                                        <p className="messageDate">Id du lien : </p>
                                        <p>{e.identifiant}</p>
                                        <p className="messageDate">Expire le : <span className="secondaryColor">{new Date(e.expirationDate).toLocaleDateString()}</span></p>
                                    </div>)
                                }
                            </div>
                        )}
                    </div>
                    <div>
                        <h3>Section dangereuse</h3>
                        <FormPost onSubmit={deleteServer}>
                            <button type="submit" className="button crimsonButton">Supprimer le serveur</button>
                        </FormPost>
                    </div>
                </div>)}

                {tab === "channels" && (<div id="channelsContent" className="settingsContent">
                    <div>
                        <h3>Créer un canal</h3>
                        <FormPost className="singleLineForm" onSubmit={createChannel}>
                            <Field id={"channelName"} label={"Nom de votre canal"} />
                            <Select id="category" label="Séléctionnez une catégorie" options={categories} />
                            <button type="submit" className="button crimsonButton">Créer le canal</button>
                        </FormPost>
                    </div>
                    <div>
                        <h3>Gérez vos canaux</h3>
                        <div>
                            {channels.map((e, i) => 
                                <div key={i}>{e.name}</div>
                            )}
                        </div>
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