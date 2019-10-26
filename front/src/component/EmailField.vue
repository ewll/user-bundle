<template>
    <v-text-field prepend-icon="mdi-account"
                  label="Email"
                  :disabled="disabled"
                  :error-messages="form.errors.email"
                  v-model="form.data.email"
    />
</template>

<script>
    export default {
        props: {
            form: Object,
            disabled: {type: Boolean, default: false},
        },
    }
</script>
