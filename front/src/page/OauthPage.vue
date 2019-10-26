<template>
    <user-form :title="titleText" :submitText="submitText" :url="formUrl" @success="success"
               :showForm="showForm" :addFormData="addFormData"
    >
        <template v-slot:default="slotProps">
            <pass-field v-if="!config.isUserExists" :form="slotProps.form" label="Придумайте пароль"/>
            <login-code-form v-if="config.isUserExists" :form="slotProps.form" :actionId="config.twofaTypeId"
                             :isStoredCode="config.isStoredTwofaCode"
                             :addFormDataKeys="['token']" url="/2fa/oauth/code"
            />
        </template>
        <template v-slot:actions>
            <v-btn href="/login" text>Вход</v-btn>
        </template>
        <template v-slot:pre>
            <v-alert v-if="config.isCodeWrong" prominent type="error">
                <v-row align="center">
                    <v-col class="grow">
                        Код авторизации не верный или устарел
                    </v-col>
                    <v-col class="shrink">
                        <v-btn href="/login" text>Вход</v-btn>
                        <v-btn href="/signup" text>Регистрация</v-btn>
                    </v-col>
                </v-row>
            </v-alert>
        </template>
    </user-form>
</template>

<script>
    import UserForm from './../component/UserForm';
    import PassField from './../component/PassField';
    import LoginCodeForm from './../component/LoginCodeForm';

    export default {
        components: {UserForm, PassField, LoginCodeForm},
        data: () => ({
            config: config,
            addFormData: {token: config.oauthToken},
            twofa: {show: false, actionId: null, isStored: false}
        }),
        computed: {
            showForm() {
                return !this.config.isCodeWrong;
            },
            formUrl() {
                return this.config.isUserExists ? '/oauth/login' : '/oauth/signup';
            },
            titleText() {
                return this.config.isUserExists ? 'Вход' : 'Регистрация';
            },
            submitText() {
                return this.config.isUserExists ? 'Войти' : 'Зарегистрироваться';
            },
        },
        methods: {
            success(response) {
                let urlParts = window.location.href.split('#');
                let sharpParams = urlParts.length === 2 ? '#' + urlParts[1] : '';
                window.location.href = response.body.redirect + sharpParams;
            },
        }
    }
</script>
