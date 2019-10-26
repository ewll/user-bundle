<template>
    <user-form title="Восстановление пароля"
               submitText="Применить" :url="'/password-recovering/recover/'+config.recoveryCode"
               @success="success" :showForm="config.isUserRecoveryFound&&showForm">
        <template v-slot:default="slotProps">
            <pass-field :form="slotProps.form" label="Новый пароль"/>
        </template>
        <template v-slot:actions>
            <v-btn href="/login" text>Отменить</v-btn>
        </template>
        <template v-slot:pre>
            <v-alert v-if="!config.isUserRecoveryFound" prominent type="error">
                <v-row align="center">
                    <v-col class="grow">
                        Код восстановления не найден или устарел
                    </v-col>
                    <v-col class="shrink">
                        <v-btn href="/password-recovering" text>Получить новый</v-btn>
                    </v-col>
                </v-row>
            </v-alert>
            <v-alert v-if="showSuccess" prominent type="success">
                <v-row align="center">
                    <v-col class="grow">
                        Пароль изменен
                    </v-col>
                    <v-col class="shrink">
                        <v-btn href="/login" text>Войти</v-btn>
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
            showForm: true,
            showSuccess: false,
        }),
        methods: {
            success() {
                this.showForm = false;
                this.showSuccess = true;
            },
        }
    }
</script>
