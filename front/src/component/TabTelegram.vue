<template>
    <div>
        <v-alert class="vuetifyAlert__noPadding" icon="mdi-numeric-1" dense>
            <div>
                Для настройки 2fa через telegram добавьте следующего бота в контакты и нажмите START.
            </div>
            <div class="text-center" style="margin: 10px 0">
                <v-chip class="mr-2" color="primary" dark
                        :href="'https://t.me/'+config.telegramBotName"
                        target="_blank">
                    <v-icon left>mdi-telegram</v-icon>
                    {{config.telegramBotName}}
                </v-chip>
            </div>
        </v-alert>
        <v-alert class="vuetifyAlert__noPadding" icon="mdi-numeric-2" dense>
            <div>
                Теперь введите код здесь, полученный от телеграмм.
            </div>
            <div>
                <v-text-field label="Код" :error-messages="form.errors.code" v-model="form.data.code"/>
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
