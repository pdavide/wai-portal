<?php

use App\Enums\Logs\EventType;
use App\Enums\Logs\ExceptionType;
use App\Enums\Logs\JobType;
use App\Enums\CredentialPermission;
use App\Enums\CredentialType;
use App\Enums\PublicAdministrationStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WebsiteAccessType;
use App\Enums\WebsiteStatus;
use App\Enums\WebsiteType;

return [

    PublicAdministrationStatus::class => [
        PublicAdministrationStatus::PENDING => 'in attesa',
        PublicAdministrationStatus::ACTIVE => 'attiva',
        PublicAdministrationStatus::SUSPENDED => 'sospesa',
    ],

    UserPermission::class => [
        UserPermission::ACCESS_ADMIN_AREA => "accesso all'area amministrativa",
        UserPermission::MANAGE_USERS => 'gestione utenti',
        UserPermission::MANAGE_WEBSITES => 'gestione siti',
        UserPermission::VIEW_LOGS => 'visualizzare i log',
        UserPermission::MANAGE_ANALYTICS => [
            'short' => 'gestione',
            'long' => 'Il permesso di gestione consente la modifica delle impostazioni relative ai dati analytics.',
        ],
        UserPermission::READ_ANALYTICS => [
            'short' => 'lettura',
            'long' => 'Il permesso di lettura consente la consultazione di tutti i dati analytics.',
        ],
        UserPermission::DO_NOTHING => 'nessun permesso',
    ],

    UserRole::class => [
        UserRole::SUPER_ADMIN => [
            'short' => 'super amministratore di ' . config('app.name_short'),
            'long' => 'Il super amministratore può gestire tutti i dati presenti in ' . config('app.name') . '.',
        ],
        UserRole::ADMIN => [
            'short' => 'amministratore',
            'long' => "L'amministratore può gestire tutti i siti web e gli utenti della sua PA.",
        ],
        UserRole::DELEGATED => [
            'short' => 'incaricato',
            'long' => "L'utente incaricato può leggere o gestire i dati analytics secondo i permessi assegnati dall'amministratore.",
        ],
        UserRole::REGISTERED => [
            'short' => 'registrato',
            'long' => "L'utente registrato deve continuare la procedura indicando la sua PA di appartenenza.",
        ],
        UserRole::DELETED => [
            'short' => 'eliminato',
            'long' => "L'utente eliminato non ha accesso a " . config('app.name') . '.',
        ],
    ],

    UserStatus::class => [
        UserStatus::INVITED => [
            'short' => 'invitato',
            'long' => "L'utente non ha ancora accettato l'invito a " . config('app.name') . '.',
        ],
        UserStatus::INACTIVE => [
            'short' => 'inattivo',
            'long' => "L'utente non ha ancora registrato la sua PA su " . config('app.name') . '.',
        ],
        UserStatus::PENDING => [
            'short' => 'in attesa',
            'long' => "L'utente è in attesa dell'attivazione su " . config('app.name') . '.',
        ],
        UserStatus::ACTIVE => [
            'short' => 'attivo',
            'long' => "L'utente è attivo e può utilizzare i servizi di " . config('app.name') . '.',
        ],
        UserStatus::SUSPENDED => [
            'short' => 'sospeso',
            'long' => "L'utente è stato sospeso e non può utilizzare i servizi di " . config('app.name') . '.',
        ],
    ],

    WebsiteStatus::class => [
        WebsiteStatus::PENDING => [
            'short' => 'in attesa',
            'long' => 'Il sito web non sta ancora tracciando il traffico. 😕',
        ],
        WebsiteStatus::ACTIVE => [
            'short' => 'attivo',
            'long' => 'Il sito web sta già tracciando il traffico! 🎉',
        ],
        WebsiteStatus::ARCHIVED => [
            'short' => 'archiviato',
            'long' => 'Il sito web è stato archiviato. 🛑',
        ],
    ],

    WebsiteType::class => [
        WebsiteType::INSTITUTIONAL => 'sito istituzionale',
        WebsiteType::INFORMATIONAL => 'sito informativo',
        WebsiteType::SERVICE => 'sito di servizi',
        WebsiteType::MOBILE => 'applicazione per dispositivi mobili',
        WebsiteType::INSTITUTIONAL_PLAY => 'sito istituzionale',
    ],

    WebsiteAccessType::class => [
        WebsiteAccessType::NO_ACCESS => 'nessun accesso',
        WebsiteAccessType::VIEW => 'sola lettura',
        WebsiteAccessType::WRITE => 'gestione analytics',
        WebsiteAccessType::ADMIN => 'amministrazione',
    ],

    CredentialType::class => [
        CredentialType::ADMIN => 'amministrativa',
        CredentialType::ANALYTICS => 'analytics',
    ],

    CredentialPermission::class => [
        CredentialPermission::READ => [
            'short' => 'lettura',
            'long' => "Il permesso di lettura consente l'interrogazione delle API analytics per le sole operazioni di lettura.",
        ],
        CredentialPermission::WRITE => [
            'short' => 'scrittura',
            'long' => "Il permesso di scrittura consente l'interrogazione delle API analytics per le operazioni di lettura e di scrittura.",
        ],
    ],

    EventType::class => [
        EventType::EXCEPTION => 'Errore',
        EventType::ANALYTICS_LOGIN => 'Login Servizio Analytics',
        EventType::PENDING_WEBSITES_CHECK_COMPLETED => 'Verifica siti web in attesa completata',
        EventType::TRACKING_WEBSITES_CHECK_COMPLETED => 'Verifica tracciamento siti web completata',
        EventType::UPDATE_PA_FROM_IPA_COMPLETED => 'Aggiornamento IPA completato',
        EventType::PUBLIC_ADMINISTRATION_REGISTERED => 'Pubblica Amministrazione registrata',
        EventType::PUBLIC_ADMINISTRATION_ACTIVATED => 'Pubblica Amministrazione attivata',
        EventType::PUBLIC_ADMINISTRATION_ACTIVATION_FAILED => 'Attivazione Pubblica Amministrazione fallita',
        EventType::PUBLIC_ADMINISTRATION_UPDATED => 'Pubblica Amministrazione aggiornata',
        EventType::PUBLIC_ADMINISTRATION_PRIMARY_WEBSITE_CHANGED => 'Sito primario modificato',
        EventType::PUBLIC_ADMINISTRATION_PURGED => 'Pubblica Amministrazione rimossa',
        EventType::USER_LOGIN => 'Utente autenticato',
        EventType::USER_LOGOUT => 'Utente sloggato',
        EventType::USER_REGISTERED => 'Utente registrato',
        EventType::USER_INVITED => 'Utente invitato',
        EventType::USER_VERIFIED => 'Email utente verificata',
        EventType::USER_ACTIVATED => 'Utente attivato',
        EventType::USER_EMAIL_CHANGED => 'Email utente modificato',
        EventType::USER_STATUS_CHANGED => 'Stato utente modificato',
        EventType::USER_SUSPENDED => 'Utente sospeso',
        EventType::USER_REACTIVATED => 'Utente riattivato',
        EventType::USER_DELETED => 'Utente eliminato',
        EventType::USER_RESTORED => 'Utente ripristinato',
        EventType::USER_WEBSITE_ACCESS_CHANGED => 'Livello di accesso a sito per utente modificato',
        EventType::WEBSITE_ADDED => 'Sito web aggiunto',
        EventType::WEBSITE_URL_CHANGED => 'URL sito web modificato',
        EventType::WEBSITE_ACTIVATED => 'Sito web attivato',
        EventType::WEBSITE_STATUS_CHANGED => 'Stato sito web aggiornato',
        EventType::WEBSITE_ARCHIVING => 'Archiviazione sito web programmata',
        EventType::WEBSITE_ARCHIVED => 'Sito web archiviato',
        EventType::WEBSITE_UNARCHIVED => 'Sito web riattivato',
        EventType::WEBSITE_PURGING => 'Rimozione sito web programmata',
        EventType::WEBSITE_PURGED => 'Sito web rimosso',
        EventType::WEBSITE_DELETED => 'Sito web cancellato',
        EventType::WEBSITE_RESTORED => 'Sito web ripristinato',
        EventType::PRIMARY_WEBSITE_NOT_TRACKING => 'Tracciamento sito istituzionale non attivo',
        EventType::USERS_INDEXING_COMPLETED => 'Aggiornamento indice utenti completato',
        EventType::WEBSITES_INDEXING_COMPLETED => 'Aggiornamento indice siti web completato',
        EventType::EXPIRED_USER_INVITATION_USED => 'Tentativo di attivazione con invito scaduto',
        EventType::USER_PASSWORD_RESET_COMPLETED => 'Utente modifica password completata',
        EventType::USER_UPDATED => 'Utente aggiornato',
        EventType::WEBSITE_UPDATED => 'Sito web aggiornato',
        EventType::CLOSED_BETA_WHITELIST_UPDATE_FAILED => 'Aggiornamento closed beta whitelist fallito',
        EventType::PURGE_PENDING_INVITATIONS_COMPLETED => 'Cancellazione vecchi inviti in attesa completata',
        EventType::ENVIRONMENT_RESET_COMPLETED => 'Reset ambiente completato',
        EventType::MAIL_SENT => 'Email inviata',
    ],

    ExceptionType::class => [
        ExceptionType::GENERIC => 'Errore generico',
        ExceptionType::ANALYTICS_ACCOUNT => 'Errore autenticazione Servizio Analytics',
        ExceptionType::ANALYTICS_SERVICE => 'Errore Servizio Analytics',
        ExceptionType::ANALYTICS_COMMAND => 'Errore comando a Servizio Analytics',
        ExceptionType::HTTP_CLIENT_ERROR => 'Errore HTTP del client (4xx)',
        ExceptionType::SERVER_ERROR => 'Errore interno del server',
        ExceptionType::TENANT_SELECTION => 'Errore pubblica amministrazione non selezionata',
        ExceptionType::IPA_INDEX_SEARCH => 'Errore ricerca indice IPA',
        ExceptionType::REDIS_INDEX_SEARCH => 'Errore ricerca indice',
        ExceptionType::INVALID_WEBSITE_STATUS => 'Errore stato sito web non valido',
        ExceptionType::INVALID_OPERATION => 'Errore comando non valido',
        ExceptionType::INVALID_USER_STATUS => 'Error stato utente non valido',
        ExceptionType::EXPIRED_INVITATION_LINK_USAGE => 'Errore link invito scaduto',
        ExceptionType::EXPIRED_VERIFICATION_LINK_USAGE => 'Errore link verifica scaduto',
        ExceptionType::SINGLE_DIGITAL_GATEWAY => 'Errore nel servizio Single Digital Gateway',
    ],

    JobType::class => [
        JobType::CLEAR_PASSWORD_TOKEN => 'Rimozione token reset password',
        JobType::UPDATE_PA_FROM_IPA => 'Aggiornamento pubbliche amministrazioni da indice IPA',
        JobType::SEND_RESET_PASSWORD_TOKEN => 'Invio token reset password',
        JobType::SEND_EMAIL_VERIFICATION_TOKEN => 'Invio token verifica email',
        JobType::PROCESS_PENDING_WEBSITES => 'Verifica siti web in attesa',
        JobType::PROCESS_USERS_INDEX => 'Aggiornamento indice utenti',
        JobType::PROCESS_WEBSITES_INDEX => 'Aggiornamento indice siti web',
        JobType::MONITOR_WEBSITES_TRACKING => 'Monitoraggio del tracciamento dei siti web',
        JobType::PURGE_PENDING_INVITATIONS => 'Cancellazione vecchi inviti in attesa',
        JobType::RESET_ENVIRONMENT => 'Reset ambiente',
    ],

];
