import { useEffect, useRef, useState } from "react";
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
  const [initialMessages, setInitialMessages] = useState([]);
  const [user, setUser] = useState(null);
  const [update, setUpdate] = useState(0);


  // todo: update serverid et channelid quand on clique sur retour

  const skipRefetch = useRef(false);

  useEffect(() => {
    // TODO: pouvoir customiser l'ordre des serveurs
    if (!serverId) return;
    
    window.history.pushState("", "", origin + "/app/" + serverId + "/" + (channelId ?? ""));

    // return apres le setchannelid de l'effet qui l'a fait rerun
    if (skipRefetch.current) {
      skipRefetch.current = false;
      return;
    }

    fetch(origin + "/app/" + serverId + "/" + (channelId ?? ""), {
      headers: { "Accept": "application/json" }
    })
      .then((body) => body.json())
      .then((json) => {
        if (channelId != json.currentChannel) {
          skipRefetch.current = true;   // ce setChannelId ne doit PAS relancer un fetch, donc on met un flag qui va trigger un return apres le 2eme effet
          setChannelId(json.currentChannel);
        }
        setRoles(json.roles);
        setChannels(json.channels);
        setServer({ serverName: json.serverName, serverId: json.serverId, serverIcon: json.serverIcon })
        setInitialMessages(json.messages)
        console.log(json);
      })
      .catch((err) => console.log(err));

  }, [channelId, serverId, update])

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

      <Chat channelId={channelId} initialMessages={initialMessages} setUpdate={setUpdate} channel={channels?.find(e => e.id == channelId)} />

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
