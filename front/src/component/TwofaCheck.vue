<template>
    <user-form title="Двухфакторная аутентификация" submitText="Войти" url="/2fa/login" @success="success">
        <template v-slot:default="slotProps">
            <v-text-field label="Код"
                          :error-messages="slotProps.form.errors.code"
                          v-model="slotProps.form.data.code"
            />
        </template>
        <template v-slot:actions>
            <v-btn href="/exit" text>Выйти</v-btn>
        </template>
    </user-form>
</template>

<script>
    import UserForm from './UserForm';

    export default {
        components: {UserForm},
        data: () => ({
            config: config,
        }),
        methods: {
            success() {
                let urlParts = window.location.href.split('#');
                let sharpParams = urlParts.length === 2 ? '#' + urlParts[1] : '';
                window.location.href = '/private' + sharpParams;
            },
        }
    }
</script>
