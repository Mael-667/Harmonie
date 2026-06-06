import { useContext, useEffect, useState } from "react";
import { UserContext } from "./modules/UserContext";
import Modal from "./modules/Modal";
import ServerSettings from './ServerSettings';
import FormPost from "./modules/FormPost";
import { copy } from "./modules/Utils";

export default function ServersPanel({ channels, channelId, server, serverId, setServerId, setChannelId }) {

  const user = useContext(UserContext);
  const [servers, setServers] = useState(null);
  const [opened, setOpened] = useState(false);
  const [settingsOpened, setSettingsOpened] = useState(false);


  // Get user's servers
  function getServers() {
    fetch(origin + "/app/getServers")
      .then((response) => response.json())
      .then((e) => {
        setServers(e);
      })
      .catch((err) => console.log(err))
  }

  useEffect(() => {
    getServers();
  }, [])

  function serverButton(server) {
    return (
      <button
        key={server.id}
        className={`button serverButton${server.id == serverId ? " serverButtonActive" : ""}`}
        data-server-id={server.id}
        aria-label={`Rejoindre le serveur : ${server.name}`}
        style={{ backgroundImage: `url(${origin}/${server.icon})` }}
        onClick={() => { setServerId(server.id); setChannelId(null); }}
      ></button>
    );
  }

  function channelsButtons(channel) {
    return <button
      id={channel.id}
      className={`channelButton ${channel.id == channelId ? "channelButtonActive" : ""}`}
      onClick={() => setChannelId(channel.id)}
    >
      #{channel.name}
    </button>

    // TODO: réintroduire les catégories

  }

  return <div id="side_panel">
    <div id="serversDetails">
      <div id="servers">
        <div id="serverList">
          {servers?.map(serverButton)}
        </div>
        <div id="newServer" className="serverButton">
          <button className="transparentButton" aria-label="Create New Server" onClick={() => setOpened(true)}>
            <i className="fa-solid fa-plus" aria-hidden="true"></i>
          </button>
          {opened && <NewServerPopup setOpened={setOpened} onCreated={getServers} setServerId={setServerId} getServers={getServers} />}
        </div>
      </div>
      <div id="serverInfo">
        <div id="serverDetails">
          <span className="serverName">{server?.serverName}</span>
          <button className="actionButton editServer" id="editServer" onClick={() => setSettingsOpened(true)}>
            <i className="fa-solid fa-ellipsis"></i>
          </button>
          {settingsOpened && <ServerSettings setSettingsOpened={setSettingsOpened} serverId={serverId}/>}
        </div>
        <div id="channels">
          {channels?.map(channelsButtons)}
        </div>
      </div>
    </div>
    {user &&
      <div id="userInfo">
        <div id="userPfp">
          <img src={`/uploads/pdp/${user.avatar}`} alt="User avatar" />
        </div>
        <div id="userHandles">
          <p id="userPseudo">{user.pseudo}</p>
          <p id="userHandle" onClick={() => copy(user.handle)}>@{user.handle}</p>
        </div>
        <div id="settings">
          <button aria-label="settings" className="transparentButton" id="profileSetting"><i className="fa-solid fa-gear" aria-hidden="true"></i></button>
        </div>
      </div>
    }
  </div>
}

function NewServerPopup({ setOpened, onCreated, setServerId, getServers }) {
  const [preview, setPreview] = useState(null);

  function updatePreview(e) {
    setPreview(URL.createObjectURL(e.target.files[0]));
  }

  function createServer(form) {
    fetch(origin + "/app/newServer", {
      method: "POST",
      // Set the FormData instance as the request body
      body: form,
    })
      .then((response) => response.json())
      .then((e) => {
        console.log(e);
        setOpened(false);
        // TODO: refresh le form apres
        onCreated();
      })
      .catch((err) => console.log(err))

  }

  function joinServer(e){
    e.preventDefault();
    let formData = new FormData(e.currentTarget);

    fetch(origin + "/app/joinServer", {
      method: "POST",
      // Set the FormData instance as the request body
      body: formData,
    })
      .then((response) => response.json())
      .then((e) => {
        console.log(e);
        setOpened(false);
        setServerId(e.serverId)
        getServers();
      })
      .catch((err) => console.log(err))
  }


  return <Modal id="popupNewServer" onClose={() => setOpened(false)}>
    <div id="addServer">
      <h2>Ajouter un nouveau serveur</h2>
      <FormPost method="post" className="singleLineForm" onSubmit={(e) => joinServer(e)}>
        <div className="field">
          <label htmlFor="serverLink">Lien du serveur</label>
          <input type="text" name="serverLink" id="serverLink" placeholder="harmonie.gg/1" />
        </div>
        <button type="submit" className="button crimsonButton">Ajouter</button>
      </FormPost>
    </div>
    <div id="createServer">
      <h2>Ou créez en un !</h2>
      <FormPost name="server" method="post" encType="multipart/form-data"
        onSubmit={(e) => { e.preventDefault(); createServer(new FormData(e.currentTarget)); }}>
        <label htmlFor="server_icon" id="serverIconLabel" style={preview ? { backgroundImage: `url(${preview})`, color: "#ffffff00" } : undefined}>
          <input type="file" id="server_icon" name="server[icon]" onChange={updatePreview} />
        </label>
        <div className="singleLineForm">
          <div className="field">
            <label htmlFor="server_name">Nom du serveur</label>
            <input type="text" id="server_name" name="server[name]" required />
          </div>
          <button type="submit" className="button crimsonButton">Créer</button>
        </div>
      </FormPost>
    </div>
  </Modal>

}