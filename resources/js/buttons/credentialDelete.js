import { upperCaseFirst } from "upper-case-first";
import Datatable from "../datatables";
import Notification from "../notification";
import I18n from "../i18n";
import AjaxButton from "../ajaxButton";
import FormButton from "../formButton";

export default (() => {
    const init = () => {
        const credentialDeleteButtons = [
            ...document.querySelectorAll(
                'a[role="button"][data-type="credentialDelete"]'
            )
        ];

        credentialDeleteButtons.map(credentialDeleteButton => {
            const isAjax = "ajax" in credentialDeleteButton.dataset;
            const currentAction = I18n.t("eliminazione");
            const confirmation = {
                title: upperCaseFirst(
                    [currentAction, I18n.t("della credenziale")].join(" ")
                ),
                body: [
                    "<p>",
                    I18n.t("Stai eliminando la credenziale"),
                    "<strong>" + credentialDeleteButton.dataset.credentialname + "</strong>",
                    "</p>",
                    "<p>" + I18n.t("Sei sicuro?") + "<p>"
                ].join(" "),
                image: "/images/website-archive.svg"
            };

            const success = ()  => {
                Notification.showNotification(
                    I18n.t("credenziale eliminata"),
                    [
                        I18n.t("La credenziale"),
                        "<strong>" +
                        credentialDeleteButton.dataset.credentialname  +
                            "</strong>",
                        I18n.t("è stata eliminata.")
                    ].join(" "),
                    "success",
                    "it-check-circle"
                );
                Datatable.reload();
            };

            isAjax &&
                AjaxButton.init(
                    credentialDeleteButton,
                    "patch",
                    confirmation,
                    success
                );
            isAjax || FormButton.init(credentialDeleteButton, "patch", confirmation);
        });
    };

    return { init };
})();
