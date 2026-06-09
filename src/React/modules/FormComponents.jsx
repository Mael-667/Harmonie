import { useState, useContext } from "react";
import { UserContext } from "./UserContext";

export function DynamicImageInput({ name, id, placeholder, tempPreview, children, ...props }) {
    const [preview, setPreview] = useState(tempPreview ?? null);

    function updatePreview(e) {
        setPreview(URL.createObjectURL(e.target.files[0]));
    }

    return <label htmlFor={id} className="dynamicImageInput" style={preview ? { backgroundImage: `url(${preview})` } : undefined} {...props}>
        <input type="file" className="imageInput" id={id} name={name} onChange={updatePreview} />
        {!preview && <span className="imgInputPlaceholder">{placeholder}</span>}
        {children}
    </label>
}

export function FormPost({ className, id, onSubmit, children, ...props }) {
    const user = useContext(UserContext);
    return <form className={className} id={id} method="post" onSubmit={onSubmit} {...props}>
        <input type="hidden" name="token" value={user?.csrfToken} />
        {children}
    </form>
}

export function Field({id, label, placeholder, defaultValue}) {
    return <div className="field">
                <label htmlFor={id}>{label}</label>
                <input type="text" name={id} id={id} placeholder={placeholder} defaultValue={defaultValue} />
            </div>
}

export function Select({id, label, options, defaultValue}){
    return <div className="field">
                <label htmlFor={id}>{label}</label>
                <select id={id} name={id}>
                    {options.map((e, i) => <option key={i} value={e ?? ""} selected={e == defaultValue ? "selected" : ""}>{e == null ? "Sans valeur" : e}</option>)}
                </select>
            </div>
}