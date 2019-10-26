<template>
    <v-text-field prepend-icon="mdi-lock"
                  :label="label"
                  :disabled="disabled"
                  type="password"
                  :error-messages="form.errors.pass"
                  v-model="form.data.pass"
    />
</template>

<script>
    export default {
        props: {
            form: Object,
            label: {Type: String, default: 'Пароль'},
            disabled: {type: Boolean, default: false},
        },
    }
</script>
