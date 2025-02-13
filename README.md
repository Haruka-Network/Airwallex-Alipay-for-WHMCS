# Airwallex Alipay For WHMCS

支付：直接拉起 Alipay+ 支付页面，支持 PC / 移动端，可自定义货币实测需要 CNY 或者开户当地货币才支持支付宝
退款：直接拉取订单并发起对应金额的退款

开发 API 版本：2024-09-27 需要权限：`Payment Acceptance` - `Payment Acceptance` - `编辑` + `查看`

Webhook 版本：2024-02-22 侦听事件：`收单` - `交易订单` - `已成功`

回调地址：`/modules/gateways/callback/haruka_airwallex_alipay.php`