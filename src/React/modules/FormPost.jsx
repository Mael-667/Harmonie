import { useContext } from "react";
import { UserContext } from "./UserContext";

export default function FormPost({ className, id, onSubmit, children, ...props}) {
    const user = useContext(UserContext);
    return <form className={className} id={id} method="post" onSubmit={onSubmit} {...props}>
        <input type="hidden" name="token" value={user.csrfToken} />
        {children}
    </form>
}