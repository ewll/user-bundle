<template>
    <user-form title="Вход" submitText="Войти" url="/login" @success="success">
        <template v-slot:default="slotProps">
            <oauth/>
            <email-field :form="slotProps.form"/>
            <pass-field :form="slotProps.form"/>
            <captcha :form="slotProps.form" color="a4b0be"/>
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
    import Captcha from './../component/Captcha';

    export default {
        components: {PassField, UserForm, EmailField, Oauth, Captcha},
        data: () => ({
            config: config,
        }),
        methods: {
            success(response) {
                this.$snack.success({text: 'Переадресация'});
                let urlParts = window.location.href.split('#');
                let sharpParams = urlParts.length === 2 ? '#' + urlParts[1] : '';
                window.location.href = response.body.redirect + sharpParams;
            },
        }
    }
</script>
