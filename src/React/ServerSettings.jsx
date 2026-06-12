import { useState, useEffect, useMemo, useContext } from "react";
import Modal from "./modules/Modal";
import { copy } from "./modules/Utils";
import { UserContext } from "./modules/UserContext";
import { DynamicImageInput, Field, FormButtonWithConfirmation, FormPost, Select } from "./modules/FormComponents";

export default function ServerSettings({ setSettingsOpened, server, channels }) {
    // set la valeur par défaut
    const [tab, setTab] = useState("properties");
    const [invitDetails, setInvitDetails] = useState(null);
    const [creatingCategory, setCreatingCategory] = useState(false);

    const user = useContext(UserContext);

    const url = (window.location.origin).replace(window.location.protocol, "").replace("//", "");

    const categories = useMemo(() => getCategories(channels), [channels])

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

    function getCategories(channels) {
        let categories = [];
        channels.forEach(c => {
            if (!categories.includes(c.category)) {
                categories.push(c.category);
            }
        })
        return categories;
    }

    function submitNewInvit(e) {
        e.preventDefault();
        const form = new FormData(e.currentTarget);
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
                window.location.replace(window.location.origin+"/app");
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

    function updateChannel(e, channelId) {
        e.preventDefault();
        let form = new FormData(e.currentTarget);
        form.append("serverId", server.serverId)
        form.append("channelId", channelId)

        fetch(origin + "/app/editChannel", {
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

    function deleteChannel(channelId) {
        // pas de <FormPost> ici (onClick), on ajoute donc le token CSRF à la main
        let form = new FormData();
        form.append("token", user?.csrfToken)
        form.append("serverId", server.serverId)
        form.append("channelId", channelId)

        fetch(origin + "/app/deleteChannel", {
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
            <nav id="serverSettingsNav" role="tablist" aria-label="Sections des paramètres du serveur">
                <button type="button" role="tab" id="tab-properties" aria-selected={tab === "properties"} aria-controls="propertiesContent" className={`navButton ${tab === "properties" ? "navButtonActive" : ""}`} onClick={() => setTab('properties')}>Propriétés du serveur</button>
                <button type="button" role="tab" id="tab-channels" aria-selected={tab === "channels"} aria-controls="channelsContent" className={`navButton ${tab === "channels" ? "navButtonActive" : ""}`} onClick={() => setTab('channels')}>Canaux de discussion</button>
                <button type="button" role="tab" id="tab-users" aria-selected={tab === "users"} aria-controls="usersContent" className={`navButton ${tab === "users" ? "navButtonActive" : ""}`} onClick={() => setTab('users')}>Gestion d'utilisateurs</button>
                <button type="button" role="tab" id="tab-roles" aria-selected={tab === "roles"} aria-controls="rolesContent" className={`navButton ${tab === "roles" ? "navButtonActive" : ""}`} onClick={() => setTab('roles')}>Gestion des rôles</button>
            </nav>

            <div id="settingsDetails">

                {tab === "properties" && (<div id="propertiesContent" role="tabpanel" aria-labelledby="tab-properties" className="settingsContent">
                    <div>
                        <h3>Modifier le serveur</h3>
                        <FormPost className={"servInfoForm"} onSubmit={editServer}>
                            <div className="servInfoEdit">
                                <DynamicImageInput name="serverIcon" id="serverIcon" tempPreview={`${origin}/uploads/serverIcon/${server.serverIcon}`}>
                                    <i className="fa-solid fa-pen imgInputIcon" aria-hidden="true"></i>
                                </DynamicImageInput>
                                <Field id={"editName"} label={"Renommez votre serveur"} defaultValue={server.serverName} minLength={1} maxLength={27}/>
                            </div>
                            <button type="submit" className="button crimsonButton" style={{ alignSelf: "end" }}>Mettre à jour</button>
                        </FormPost>
                    </div>
                    <div>
                        <h3>Créer une invitation</h3>
                        <FormPost className="singleLineForm" id="newInvitForm"
                            onSubmit={submitNewInvit}>
                            <div className="field">
                                <label htmlFor="newInvit">Modifiez l'url selon vos besoins</label>
                                <span id="invitUrlConteneur"><span id="invitUrl">{url + "/join/"}</span><input type="text" name="newInvit" id="newInvit" required defaultValue={invitDetails.randomId} minLength={1} maxLength={27} /></span>
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
                                    <button type="button" key={i} className="textButton activeInvitation" onClick={() => { copy(`${url}/join/${e.identifiant}`) }}>
                                        <p className="messageDate">Id du lien : </p>
                                        <p>{e.identifiant}</p>
                                        <p className="messageDate">Expire le : <span className="secondaryColor">{new Date(e.expirationDate).toLocaleDateString()}</span></p>
                                    </button>)
                                }
                            </div>
                        )}
                    </div>
                    <div>
                        <h3>Section dangereuse</h3>
                        <FormButtonWithConfirmation label={"Supprimer le serveur"} onSubmit={deleteServer} confirmationMessage={"Voulez vous vraiment supprimer ce serveur ?"} />
                    </div>
                </div>)}

                {tab === "channels" && (<div id="channelsContent" role="tabpanel" aria-labelledby="tab-channels" className="settingsContent">
                    <div>
                        <h3>Créer un canal</h3>
                        <FormPost className="singleLineForm" onSubmit={createChannel}>
                            <Field id={"channelName"} label={"Nom de votre canal"} />
                            <div className="selectCategory">
                                {categories.length != 0 && !creatingCategory && <Select id="category" label="Séléctionnez une catégorie" options={categories} />}
                                {creatingCategory && <Field id="category" label="Nom de votre nouvelle catégorie" required minLength={1} maxLength={27} />}
                                <button type="button" className="actionButton formActionButton" aria-label="Créer une catégorie" onClick={!creatingCategory ? () => setCreatingCategory(true) : () => setCreatingCategory(false)}>{categories.length == 0 && !creatingCategory && "Ajouter une catégorie"} {!creatingCategory ? <i className="fa-solid fa-circle-plus"></i> : <i className="fa-solid fa-circle-xmark"></i>}</button>
                            </div>
                            <button type="submit" className="button crimsonButton">Créer le canal</button>
                        </FormPost>
                    </div>
                    <div>
                        <h3>Gérez vos canaux</h3>
                        <div className="listConteneur" style={{ flexDirection: "column", padding: "0.3rem" }}>
                            {channels.map((e, i) =>
                                <ChannelRow key={i} channel={e} categories={categories} onUpdate={updateChannel} onDelete={deleteChannel} />
                            )}
                        </div>
                    </div>

                </div>)}

                {tab === "users" && (<div id="usersContent" role="tabpanel" aria-labelledby="tab-users">

                </div>)}

                {tab === "roles" && (<div id="rolesContent" role="tabpanel" aria-labelledby="tab-roles">

                </div>)}
            </div>
        </div>
    </Modal>
}

// Une ligne d'édition de canal : état `creatingCategory` propre à chaque ligne
// (sinon le toggle « ajouter une catégorie » serait partagé entre toutes les lignes).
function ChannelRow({ channel, categories, onUpdate, onDelete }) {
    const [creatingCategory, setCreatingCategory] = useState(false);

    return <FormPost className="channelDetails" onSubmit={(event) => onUpdate(event, channel.id)}>
        <div className="editChannelName">
        #<input
            name="editedChannelName"
            defaultValue={channel.name}   /* ex-`editInput.value = oldContent` */
            placeholder="Nommez votre canal"
            aria-label="Nom de votre canal"
            className="editedMessage"
        />
        </div>
        <div className="selectCategory">
            {categories.length != 0 && !creatingCategory && <Select id="category" label="Séléctionnez une catégorie" options={categories} defaultValue={channel.category} />}
            {creatingCategory && <Field id="category" required label="Nom de votre nouvelle catégorie" />}
            <button type="button" className="actionButton formActionButton" aria-label="Créer une catégorie" onClick={() => setCreatingCategory(!creatingCategory)}>{categories.length == 0 && !creatingCategory && "Ajouter une catégorie"} {!creatingCategory ? <i className="fa-solid fa-circle-plus"></i> : <i className="fa-solid fa-circle-xmark"></i>}</button>
        </div>
        <button className="actionButton formActionButton" type="button" aria-label="Supprimer le canal" onClick={() => onDelete(channel.id)}>
            <i className="fa-solid fa-trash-can" aria-hidden="true"></i>
        </button>
        <button type="submit" className="button crimsonButton">Mettre à jour le canal</button>
    </FormPost>
}