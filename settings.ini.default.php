<?php
return [

    // The API key for OpenAI
    "api_key" => "OpenAI API Key",

    // The model that will be used for meaning verification requests
    "model_meaning_verification" => "gpt-4.1-mini",

    // The model that will be used for all other requests
    "model_core" => "o3-mini",

    // The system prompt that will be prepended to every request as system message
    "system_prompt" => "You are a helpful Live Helper Chat Bot. You answer questions based on file search. If you don't know the answer, respond with \"I can only help with Live Helper Chat related questions.\" Provide the most relevant answer to the visitor's question, not exceeding 100 words. Include a link for more information about your answer.",
]
?>