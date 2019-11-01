<template>
    <user-form title="Восстановление пароля"
               submitText="Восстановить" url="/password-recovering/init"
               @success="success" :showForm="showForm">
        <template v-slot:default="slotProps">
            <email-field :form="slotProps.form"/>
            <captcha :form="slotProps.form"/>
        </template>
        <template v-slot:actions>
            <v-btn href="/login" text>Назад</v-btn>
        </template>
        <template v-slot:pre>
            <v-alert v-if="successRecoveringInit" :value="true" type="success">
                Письмо с инструкциями по восстановлению пароля отправлено вам на электронную почту.
            </v-alert>
        </template>
    </user-form>
</template>

<script>
    import UserForm from './../component/UserForm';
    import EmailField from './../component/EmailField';
    import Captcha from './../component/Captcha';

    export default {
        components: {UserForm, EmailField, Captcha},
        data: () => ({
            config: config,
            successRecoveringInit: false,
            showForm: true,
        }),
        methods: {
            success() {
                this.showForm = false;
                this.successRecoveringInit = true;
            },
        }
    }
</script>
