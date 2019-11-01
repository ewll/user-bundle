<template>
    <user-form title="Регистрация" submitText="Зарегистрироваться" url="/oauth/signup" @success="success"
               :showForm="showForm" :addFormData="addFormData"
    >
        <template v-slot:default="slotProps">
            <pass-field :form="slotProps.form" label="Придумайте пароль"/>
        </template>
        <template v-slot:actions>
            <v-btn href="/login" text>Назад</v-btn>
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
            <v-alert v-if="config.isEmailNotReceived" prominent type="error">
                <v-row align="center">
                    <v-col class="grow">
                        Похоже, вы не разрешили передачу вашего email. Попробуйте еще раз.
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

    export default {
        components: {UserForm, PassField},
        data: () => ({
            config: config,
            addFormData: {token: config.tokenCode},
            twofa: {show: false, actionId: null, isStored: false}
        }),
        computed: {
            showForm() {
                return !this.config.isCodeWrong && !this.config.isEmailNotReceived;
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
