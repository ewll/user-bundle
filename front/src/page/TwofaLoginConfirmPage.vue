<template>
    <user-form title="Двухфакторная аутентификация" submitText="Войти" url="/2fa/login" @success="success"
               :showForm="!config.isCodeWrong" :addFormData="{token:config.tokenCode}"
    >
        <template v-slot:default="slotProps">
            <login-code-form :form="slotProps.form" :actionId="config.twofaActionId" :token="config.tokenCode"
                             :isStoredCode="config.isStoredTwofaCode" url="/2fa/login/code"
            />
        </template>
        <template v-slot:actions>
            <v-btn href="/exit" text>Выйти</v-btn>
        </template>
        <template v-slot:pre>
            <v-alert v-if="config.isCodeWrong" prominent type="error">
                <v-row align="center">
                    <v-col class="grow">
                        Токен не найден или устарел
                    </v-col>
                    <v-col class="shrink">
                        <v-btn href="/login" text>Назад</v-btn>
                    </v-col>
                </v-row>
            </v-alert>
        </template>
    </user-form>
</template>

<script>
    import UserForm from './../component/UserForm';
    import LoginCodeForm from './../component/LoginCodeForm';

    export default {
        components: {UserForm, LoginCodeForm},
        data: () => ({
            config: config,
        }),
        methods: {
            success() {
                this.$snack.success({text: 'Переадресация'});
                let urlParts = window.location.href.split('#');
                let sharpParams = urlParts.length === 2 ? '#' + urlParts[1] : '';
                window.location.href = '/private' + sharpParams;
            },
        }
    }
</script>
