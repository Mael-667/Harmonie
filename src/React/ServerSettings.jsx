import { useState, useEffect } from "react";
import Modal from "./modules/Modal";
import FormPost from "./modules/FormPost";

export default function ServerSettings({setSettingsOpened, serverId}) {
    // set la valeur par défaut
    const [tab, setTab] = useState("properties");
    const [invitDetails, setInvitDetails] = useState(null);
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
            setInvitDetails({randomId: e.randomId, invitations: e.invitations});
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
        .then(() => {
            copy(`${url}/join/${form.get("newInvit")}`)
        })
        .catch((err) => {
            // indiquer a l'utilisateur que l'id est deja pris
            console.log(err);
        })
    }

    function copy(content) {
      if (navigator.clipboard) {
          navigator.clipboard.writeText(content);
      } else {
          // contexte non sécurisé (HTTP hors localhost) : fallback
          const ta = document.createElement("textarea");
          ta.value = content;
          ta.style.position = "fixed";
          ta.style.opacity = "0";
          document.body.appendChild(ta);
          ta.select();
          document.execCommand("copy");
          ta.remove();
      }
  }

    if (!invitDetails) return null;   // ou un <Modal>…Chargement…</Modal>

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
                                    <span id="invitUrlConteneur"><span id="invitUrl">{url + "/join/"}</span><input type="text" name="newInvit" id="newInvit" defaultValue={invitDetails.randomId}/></span>
                                </div>
                                <button type="submit" className="button crimsonButton">Créer et copier le lien</button>
                            </FormPost>
                        </div>
                        <div>
                            <h3>Liste des invitations</h3>
                            <span className="smallText secondaryColor">Cliquez pour copier !</span>
                            <div className="listConteneur">
                                {invitDetails.invitations.map((e, i) => 
                                    <div key={i} className="activeInvitation" onClick={() => {copy(`${url}/join/${e.identifiant}`)}}>
                                        <p><span className="messageDate">Url : </span>/join/{e.identifiant}</p>
                                        <p className="messageDate">Expire le : <span className="secondaryColor">{new Date(e.expirationDate).toLocaleDateString()}</span></p>
                                    </div>)
                                }
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