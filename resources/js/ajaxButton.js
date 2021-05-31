import Confirmation from './confirmation';
import Datatable from './datatables';
import Notification from './notification';
import I18n from './i18n';

export default (() => {
    const buttonAction = (button, method, success, notModified) => {
        button.disabled = true;
        button.classList.add('loading', 'disabled');
        button.setAttribute('aria-disabled', true);

        return axios[method](button.getAttribute('href'))
            .then(response => success(response))
            .catch(error => {
                if (error.response && error.response.status === 303) {
                    notModified && notModified();
                    notModified || Notification.showNotification(I18n.t('operazione non effettuata'), I18n.t("L'azione richiesta è già stata effettuata."), 'info', 'it-info-circle');
                    Datatable.reload();
                } else if (!error.response || error.response.status !== 401) {
                    Notification.showServerErrorNotification();
                }
            })
            .finally(() => {
                button.disabled = false;
                button.classList.remove('loading', 'disabled');
                button.setAttribute('aria-disabled', false);
            });
    };

    const init = (button, method, confirmation, success, notModified) => {
        // Remove all previously attached event listeners
        const buttonClone = button.cloneNode(true);
        button.parentNode.replaceChild(buttonClone, button);
        button = buttonClone;

        button.addEventListener('click', event => {
            event.preventDefault();

            confirmation && Confirmation.showConfirmation(confirmation.title, confirmation.body, confirmation.image).then(() => {
                buttonAction(button, method, success, notModified);
            }).catch(() => {});

            confirmation || buttonAction(button, method, success, notModified);
        });

        button.disabled = false;
        button.classList.remove('disabled');
        button.setAttribute('aria-disabled', false);
    };

    return { init };
})();

