<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Búsquedas indexables inactivas
    |--------------------------------------------------------------------------
    |
    | Con true, las páginas /{locale}/{país}/busqueda/{query} responden
    | "noindex,follow" salvo que la búsqueda guardada esté activa. Evita
    | indexar decenas de miles de búsquedas que no traen visitas.
    |
    */

    'noindex_inactive' => (bool) env('SEARCH_NOINDEX_INACTIVE', false),

];
