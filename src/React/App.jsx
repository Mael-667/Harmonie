import { useEffect, useState } from "react";
import ServersPanel from "./ServersPanel";
import Chat from "./Chat";
import { UserContext } from "./modules/UserContext";
import { PermissionProvider } from "./hooks/usePermission";

export default function App() {
  // Charge automatiquement les info du serveur si l'utilisateur accède spécifiquement a un lien contenant id serveur et ou channel
  const segments = window.location.pathname.split("/").filter(Boolean);
  let urlServerId, urlChannelId;
  if (segments[0] == "app") {
    urlServerId = !isNaN(segments[1]) ? segments[1] : null;
    urlChannelId = !isNaN(segments[2]) ? segments[2] : null;
  }

  const [serverId, setServerId] = useState(urlServerId);
  const [channelId, setChannelId] = useState(urlChannelId);
  const [channels, setChannels] = useState(null);
  const [server, setServer] = useState(null);
  const [roles, setRoles] = useState(null);
  const [messages, setMessages] = useState([]);
  const [user, setUser] = useState(null);


  // TODO: token crsf a refaire
  useEffect(() => {
    // TODO: pouvoir customiser l'ordre des serveurs
    if (!serverId) return;
    // TODO: Mettre la version react
    window.history.pushState("", "", origin + "/app/" + serverId + "/" + (channelId ?? ""));
    fetch(origin + "/app/" + serverId + "/" + (channelId ?? ""), {
      headers: { "Accept": "application/json" }
    })
      .then((body) => body.json())
      .then((json) => {
        if (channelId != json.currentChannel) setChannelId(json.currentChannel);
        setRoles(json.roles);
        setChannels(json.channels);
        setServer({ serverName: json.serverName, serverId: json.serverId })
        setMessages(json.messages)
        console.log(json);
      })
      .catch((err) => console.log(err));

  }, [channelId, serverId])

  useEffect(() => {
    fetch(origin + "/app/me")
      .then((response) => response.json())
      .then((e) => {
        setUser(e);
      })
      .catch((err) => console.log(err))
  }, [])



  return <UserContext value={user}>
    <PermissionProvider roles={roles} userId={user?.id}>
      <ServersPanel server={server} serverId={serverId} setServerId={setServerId} channels={channels} channelId={channelId} setChannelId={setChannelId} />

      <Chat channelId={channelId} messages={messages} setMessages={setMessages} />

      <div id="social">
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
    </PermissionProvider>
  </UserContext>
}
