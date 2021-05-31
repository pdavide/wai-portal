<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Il campo :attribute deve essere accettato.',
    'active_url' => 'Il campo :attribute non è un URL valido.',
    'after' => 'Il campo :attribute deve essere successivo al :date.',
    'after_or_equal' => 'Il campo :attribute deve essere successivo o uguale al :date.',
    'alpha' => 'Il campo :attribute può contenere solo lettere.',
    'alpha_dash' => 'Il campo :attribute può contenere solo lettere, numeri e trattini.',
    'alpha_num' => 'Il campo :attribute può contenere solo lettere e numeri.',
    'alpha_name' => 'Il campo :attribute può contenere solo lettere, spazi e apostrofo.',
    'alpha_site' => 'Il campo :attribute può contenere solo lettere, spazi e punteggiatura.',
    'array' => 'Il campo :attribute deve essere un array.',
    'before' => 'Il campo :attribute deve essere precedente al :date.',
    'before_or_equal' => 'Il campo :attribute deve essere precedente o uguale al :date.',
    'between' => [
        'numeric' => 'Il campo :attribute deve trovarsi tra :min - :max.',
        'file' => 'Il campo :attribute deve trovarsi tra :min - :max kilobytes.',
        'string' => 'Il campo :attribute deve trovarsi tra :min - :max caratteri.',
        'array' => 'Il campo :attribute deve avere tra :min - :max elementi.',
    ],
    'boolean' => 'Il campo :attribute deve essere vero o falso.',
    'confirmed' => 'Il campo di conferma :attribute non coincide.',
    'date' => 'Il campo :attribute non è una data valida.',
    'date_format' => 'Il campo :attribute non coincide con il formato :format.',
    'different' => 'Il campo :attribute e :other devono essere differenti.',
    'digits' => 'Il campo :attribute deve essere di :digits cifre.',
    'digits_between' => 'Il campo :attribute deve essere tra :min e :max cifre.',
    'dimensions' => "Le dimensioni dell'immagine di :attribute non sono valide.",
    'distinct' => 'Il campo :attribute contiene un valore duplicato.',
    'email' => 'Il campo :attribute non è valido.',
    'exists' => 'Il campo :attribute selezionato non è valido.',
    'file' => 'Il campo :attribute deve essere un file.',
    'filled' => 'Il campo :attribute deve contenere un valore.',
    'image' => ":attribute deve essere un'immagine.",
    'in' => 'Il campo :attribute selezionato non è valido.',
    'in_array' => 'Il valore del campo :attribute non esiste in :other.',
    'integer' => 'Il campo :attribute deve essere un numero intero.',
    'ip' => 'Il campo :attribute deve essere un indirizzo IP valido.',
    'ipv4' => 'Il campo :attribute deve essere un indirizzo IPv4 valido.',
    'ipv6' => 'Il campo :attribute deve essere un indirizzo IPv6 valido.',
    'json' => 'Il campo :attribute deve essere una stringa JSON valida.',
    'max' => [
        'numeric' => 'Il campo :attribute non può essere superiore a :max.',
        'file' => 'Il campo :attribute non può essere superiore a :max kilobytes.',
        'string' => 'Il campo :attribute non può contenere più di :max caratteri.',
        'array' => 'Il campo :attribute non può avere più di :max elementi.',
    ],
    'mimes' => 'Il campo :attribute deve essere del tipo: :values.',
    'mimetypes' => 'Il campo :attribute deve essere del tipo: :values.',
    'min' => [
        'numeric' => 'Il campo :attribute deve essere almeno :min.',
        'file' => 'Il campo :attribute deve essere almeno di :min kilobytes.',
        'string' => 'Il campo :attribute deve contenere almeno :min caratteri.',
        'array' => 'Il campo :attribute deve avere almeno :min elementi.',
    ],
    'not_in' => 'Il valore selezionato per :attribute non è valido.',
    'not_regex' => 'The :attribute format is invalid.',
    'numeric' => 'Il campo :attribute deve essere un numero.',
    'present' => 'Il campo :attribute deve essere presente.',
    'regex' => 'Il formato del campo :attribute non è valido.',
    'required' => 'Il campo :attribute è obbligatorio.',
    'required_if' => 'Il campo :attribute è obbligatorio quando :other è :value.',
    'required_unless' => 'Il campo :attribute è obbligatorio a meno che :other sia in :values.',
    'required_with' => 'Il campo :attribute è obbligatorio quando :values è presente.',
    'required_with_all' => 'Il campo :attribute è obbligatorio quando :values è presente.',
    'required_without' => 'Il campo :attribute è obbligatorio quando :values non è presente.',
    'required_without_all' => 'Il campo :attribute è obbligatorio quando nessuno di :values è presente.',
    'same' => 'Il campo :attribute e :other devono coincidere.',
    'size' => [
        'numeric' => 'Il campo :attribute deve essere :size.',
        'file' => 'Il campo :attribute deve essere :size kilobytes.',
        'string' => 'Il campo :attribute deve contenere :size caratteri.',
        'array' => 'Il campo :attribute deve contenere :size elementi.',
    ],
    'string' => 'Il campo :attribute deve essere una stringa.',
    'timezone' => 'Il campo :attribute deve essere una zona valida.',
    'unique' => 'Il campo :attribute è già stato utilizzato.',
    'uploaded' => 'Il campo :attribute non è stato caricato.',
    'url' => 'Il formato del campo :attribute non è valido.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'password' => [
            'regex' => 'La password scelta non è abbastanza complessa.',
        ],
        'ipa_code' => [
            'required' => 'Pubblica amministrazione non valida.',
            'exists' => 'Pubblica amministrazione non valida.',
        ],
        'slug' => [
            'required' => 'Sito web non valido.',
            'exists' => 'Sito web non valido.',
        ],
        'uuid' => [
            'required' => 'Utente non valido.',
            'exists' => 'Utente non valido.',
        ],
        'fiscal_number' => [
            'invalid_format' => 'Codice fiscale formalmente non valido.'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'nome',
        'username' => 'nome utente',
        'family_name' => 'cognome',
        'fiscal_number' => 'codice fiscale',
        'is_admin' => 'utente amministratore',
        'password' => 'password',
        'password_confirmation' => 'conferma password',
        'email' => 'indirizzo email',
        'token' => 'codice di reset',
        'accept_terms' => 'termini del servizio',
        'correct_confirmation' => 'conferma dati',
        'website_name' => 'nome del sito',
        'permissions' => 'permessi',
        'type' => 'tipologia',
        'city' => 'città',
        'country' => 'paese',
        'address' => 'indirizzo',
        'phone' => 'telefono',
        'mobile' => 'cellulare',
        'age' => 'età',
        'sex' => 'sesso',
        'gender' => 'genere',
        'day' => 'giorno',
        'month' => 'mese',
        'year' => 'anno',
        'hour' => 'ora',
        'public_administration_name' => 'pubblica amministrazione',
        'url' => 'URL',
        'ipa_code' => 'pubblica amministrazione',
        'rtd_name' => 'nominativo RTD',
        'rtd_mail' => 'email RTD',
        'rtd_pec' => 'PEC RTD',
        'county' => 'provincia',
        'region' => 'regione',
        'slug' => 'sito web',
        'uuid' => 'utente',
        'minute' => 'minuto',
        'second' => 'secondo',
        'title' => 'titolo',
        'content' => 'contenuto',
        'description' => 'descrizione',
        'excerpt' => 'estratto',
        'date' => 'data',
        'time' => 'ora',
        'available' => 'disponibile',
        'size' => 'dimensione',
        'start_date' => 'data di inizio',
        'end_date' => 'data di fine',
        'start_time' => 'orario di inizio',
        'end_time' => 'orario di fine',
        'credential_name' => 'nome della credenziale',
    ],
    'errors' => [
        'last_admin' => 'Deve restare almeno un utente amministratore per ogni PA.',
        'last_website_enabled' => "Non è possibile rimuovere l'utente :user perché questo è l'unico sito per il quale è abilitato.",
        'permissions' => 'È necessario selezionare tutti i permessi correttamente.',
        'url_public_administration' => "L'indirizzo inserito appartiene a un'altra pubblica amministrazione."
    ]
];
