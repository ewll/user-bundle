<template>
    <div>
        <v-alert class="vuetifyAlert__noPadding" icon="mdi-numeric-1" dense>
            <div>
                Для настройки 2fa через google скачайте приложение Google Authenticator на свой мобильный телефон.
            </div>
            <div class="text-center" style="margin: 10px 0">
                <v-chip class="mr-2" color="#0088cc" dark
                        href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2"
                        target="_blank">
                    <v-icon left>mdi-google-play</v-icon>
                    Play Market
                </v-chip>
                <v-chip class="mr-2" color="#0088cc" dark
                        href="https://apps.apple.com/ru/app/google-authenticator/id388497605"
                        target="_blank">
                    <v-icon left>mdi-apple</v-icon>
                    App Store
                </v-chip>
            </div>
        </v-alert>
        <v-alert class="vuetifyAlert__noPadding" icon="mdi-numeric-2" dense>
            <div>
                <div>
                    Теперь отсканируйте QR-код или введите секрет вручную в приложении.
                </div>
                <v-alert class="vuetifyAlert__noPadding caption" colored-border dense>
                    Так же советуем записать этот секрет на отдельном носителе, так как приложение не хранит его в
                    облаке и по статистике каждый второй пользователь теряет код и обращается за восстановлением
                    доступа к аккаунту.
                    <br><span class="bold">Восстановление доступа занимает около недели!</span>
                    <br><span class="bold">Не храните секрет совместно с паролем!</span>
                </v-alert>
            </div>
            <div class="text-center">
                <div>
                    <img :src="config.googleSecretUrl" alt="Google QR-код" title="Google QR-код">
                </div>
                <div>
                    Секрет: {{config.googleSecret}}
                </div>
            </div>
        </v-alert>
        <v-alert class="vuetifyAlert__noPadding" icon="mdi-numeric-3" dense>
            <div>
                И, наконец, введите код из приложения здесь.
            </div>
            <div>
                <v-text-field label="Код"
                              :error-messages="form.errors.code"
                              v-model="form.data.code"
                />
            </div>
        </v-alert>
    </div>
</template>

<script>
    export default {
        props: {
            form: Object,
        },
        data: () => ({
            config: config,
        }),
    }
</script>
