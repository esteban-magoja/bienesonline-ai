<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | This value is the API key for your OpenAI account. You can find this
    | key on the OpenAI website. It is used to authenticate with the
    | OpenAI API.
    |
    */
    'api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Embeddings Model
    |--------------------------------------------------------------------------
    |
    | This value is the model that will be used to generate embeddings.
    | You can change this to any model that is available on the OpenAI
    | API.
    |
    */
    'embeddings_model' => env('EMBEDDINGS_MODEL', 'text-embedding-ada-002'),
];
