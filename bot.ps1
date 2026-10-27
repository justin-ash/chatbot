$token = "8240934169:AAG8gpuimhGQLPzjPKI8Fp29CPQoMeYg3a8"
$secret = "tg_sec_9f8e7d6c5b4a3f2e1d0c9b8a7f6e5d4c3b2a1f0e9d8c7b6a"

$body = @{
    url = "https://bot.hiarc.in/api/telegram/webhook"
    secret_token = $secret
    allowed_updates = @("message", "callback_query", "pre_checkout_query")
} | ConvertTo-Json -Depth 5

Invoke-RestMethod `
    -Uri "https://api.telegram.org/bot$token/setWebhook" `
    -Method Post `
    -ContentType "application/json" `
    -Body $body