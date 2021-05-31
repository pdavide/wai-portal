import SwaggerUIBundle from "swagger-ui";
import Notification from './notification';
import I18n from './i18n';
import "swagger-ui/dist/swagger-ui.css";

export default (() => {
    const init = () => {
        const swaggerDiv = document.getElementById("swagger-ui");

        if (swaggerDiv) {
            const isProduction = "production" === swaggerDiv.dataset.environment;
            const selectCredential = document.getElementById(
                "select-credential"
            );

            const disableTryItOutPlugin = () => {
                return {
                    statePlugins: {
                        spec: {
                            wrapSelectors: {
                                allowTryItOutFor: () => () =>
                                    !isProduction && selectCredential
                            }
                        }
                    }
                };
            };

            const disableAuthorizeButtonPlugin = () => {
                return (!isProduction && selectCredential) ? {} : {
                    wrapComponents: {
                        authorizeBtn: () => () => null
                    }
                };
            };

            const ui = SwaggerUIBundle({
                url: "/api/specification",
                dom_id: "#swagger-ui",
                plugins: [disableTryItOutPlugin, disableAuthorizeButtonPlugin]
            });

            if (selectCredential && !isProduction) {
                selectCredential.addEventListener('change', () => {
                    const selectedCredential = selectCredential[selectCredential.selectedIndex];
                    if (selectedCredential.value) {
                        ui.initOAuth({
                            clientId: selectedCredential.dataset.clientId,
                            clientSecret: selectedCredential.dataset.clientSecret,
                            appName: selectedCredential.text,
                            additionalQueryStringParams: {
                                grant_type: "client_credentials"
                            }
                        });

                        Notification.showNotification(I18n.t('credenziale selezionata'), I18n.t("Adesso è possibile usare la credenziale con il bottone 'Authorize'."), 'info', 'it-info-circle');
                        document.querySelector('.swagger-ui .auth-wrapper').style.setProperty('display', 'flex', 'important');
                    }
                });
            }
        }
    };

    return { init };
})();
