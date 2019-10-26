<template>
    <user-form title="Вход" submitText="Войти" url="/login" @success="success" @twofaError="twofaError">
        <template v-slot:default="slotProps">
            <oauth/>
            <email-field :disabled="twofa.show" :form="slotProps.form"/>
            <pass-field :disabled="twofa.show" :form="slotProps.form"/>
            <login-code-form v-if="twofa.show" :form="slotProps.form" :actionId="twofa.actionId"
                             :isStoredCode="twofa.isStored"
                             :addFormDataKeys="['email', 'pass']" url="/2fa/login/code"
            />
        </template>
        <template v-slot:actions>
            <v-btn href="/signup" text>Регистрация</v-btn>
            <v-tooltip bottom>
                <template v-slot:activator="{ on }">
                    <v-btn href="/password-recovering" style="margin-left:0" v-on="on" icon>
                        <v-icon>mdi-lock-question</v-icon>
                    </v-btn>
                </template>
                <span>Восстановление пароля</span>
            </v-tooltip>
        </template>
        <template v-slot:pre>
            <v-card v-if="config.emailConfirmed">
                <v-alert :value="true" type="success">
                    Email адрес подтвержден!<br>Теперь вы можете войти.
                </v-alert>
            </v-card>
        </template>
    </user-form>
</template>

<script>
    import UserForm from './../component/UserForm';
    import EmailField from './../component/EmailField';
    import PassField from './../component/PassField';
    import Oauth from './../component/Oauth';
    import LoginCodeForm from './../component/LoginCodeForm';

    export default {
        components: {PassField, UserForm, EmailField, Oauth, LoginCodeForm},
        data: () => ({
            config: config,
            twofa: {show: false, actionId: null, isStored: false}
        }),
        methods: {
            success(response) {
                let urlParts = window.location.href.split('#');
                let sharpParams = urlParts.length === 2 ? '#' + urlParts[1] : '';
                window.location.href = response.body.redirect + sharpParams;
            },
            twofaError(response) {
                this.twofa.isStored = response.body.data.twofa.isStoredCode;
                this.twofa.actionId = response.body.data.twofa.actionId;
                this.twofa.show = true;
            },
        }
    }
</script>
