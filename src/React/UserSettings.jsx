import { DynamicImageInput, Field, FormButtonWithConfirmation, FormPost } from "./modules/FormComponents";
import Modal from "./modules/Modal";

export default function UserSettings({ close, user }) {

    function editUser(e) {
        e.preventDefault();
        let form = new FormData(e.currentTarget);

        fetch(origin + "/app/editUser", {
            method: "POST",
            body: form,
        })
        .then((response) => response.json())
        .then((e) => {
            console.log(e);
        })
        .catch((err) => console.log(err))
    }

    function deleteProfile(e) {
        e.preventDefault();
        let form = new FormData(e.currentTarget);

        fetch(origin + "/app/deleteUser", {
            method: "POST",
            body: form,
        })
        .then((response) => response.json())
        .then((e) => {
            console.log(e);
            window.location.replace(window.location.origin+"/logout");
        })
        .catch((err) => console.log(err))
    }
    
    function logout(e){
        e.preventDefault();
        window.location.replace(window.location.origin+"/logout");
    }

    return <Modal onClose={close} className="userSettings">
        <div>
            <h3>Modifier votre profil</h3>
            <FormPost className={"servInfoForm"} onSubmit={editUser}>
                <div className="servInfoEdit">
                    <DynamicImageInput name="avatar" id="avatar" tempPreview={`/uploads/pdp/${user.avatar}`}>
                        <i className="fa-solid fa-pen imgInputIcon" aria-hidden="true"></i>
                    </DynamicImageInput>
                    <Field id={"editPseudo"} label={"Modifiez votre pseudo"} defaultValue={user.pseudo} minLength={1} maxLength={25}/>
                    <Field id={"editHandle"} label={"Modifiez votre handle"} defaultValue={user.handle} minLength={1} maxLength={27}/>
                </div>
                <button type="submit" className="button crimsonButton" style={{ alignSelf: "end" }}>Mettre à jour</button>
            </FormPost>
        </div>
        <div>
            <h3>Section dangereuse</h3>
            <FormButtonWithConfirmation label={"Se déconnecter"} onSubmit={logout} confirmationMessage={"Vous allez être déconnecté, continuer ?"}></FormButtonWithConfirmation>
            <br />
            <br />
            <FormButtonWithConfirmation label={"Supprimer votre profil"} onSubmit={deleteProfile} confirmationMessage={"Voulez vous vraiment supprimer votre profil ?"} />
        </div>
    </Modal>
}