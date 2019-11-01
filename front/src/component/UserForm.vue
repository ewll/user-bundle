<template>
    <div>
        <slot name="pre"></slot>
        <v-card v-if="showForm" class="elevation-12" style="min-width: 250px">
            <v-form @submit.prevent="submit">
                <v-toolbar dark color="primary">
                    <v-toolbar-title>{{title}}</v-toolbar-title>
                </v-toolbar>
                <v-card-text>
                    <!--<slot v-bind:form="form" v-bind:disabled="isContentDisabled"></slot>-->
                    <slot v-bind:form="form"></slot>
                    <!--<login-code-form v-if="showTwofaCode" :form="form" :actionId="twofaActionId"-->
                                     <!--:isStoredCode="isStoredTwofaCode"-->
                                     <!--:addFormDataKeys="codeFormAddDataKeys" :url="codeFormUrl"-->
                    <!--/>-->
                    <div class="error--text formError">
                        <span v-if="formError">{{ formError }}</span>
                    </div>
                </v-card-text>
                <v-card-actions>
                    <slot name="actions"></slot>
                    <v-spacer></v-spacer>
                    <v-btn type="submit" color="primary" :disabled="form.loading">{{submitText}}</v-btn>
                </v-card-actions>
            </v-form>
        </v-card>
    </div>
</template>

<script>
    export default {
        props: {
            title: String,
            submitText: String,
            url: String,
            success: Function,
            twofaError: Function,
            error: Function,
            showForm: {type: Boolean, default: true},
            addFormData: {
                type: Object,
                default() {
                    return {};
                }
            },
        },
        data: () => ({
            config: config,
            form: null,
        }),
        created() {
            this.form = Main.default.initForm();
            for (let i in this.addFormData) {
                this.form.data[i] = this.addFormData[i];
            }
        },
        watch: {
            'addFormData': {
                handler: function () {
                    for (let i in this.addFormData) {
                        this.form.data[i] = this.addFormData[i];
                    }
                },
                deep: true,
            },
        },
        computed: {
            formError() {
                if (undefined !== this.form.errors.form && null !== this.form.errors.form) {
                    return this.form.errors.form;
                }
                for (let i in this.addFormData) {
                    if (undefined !== this.form.errors[i] && null !== this.form.errors[i]) {
                        return this.form.errors[i];
                    }
                }

                return null;
            },
        },
        methods: {
            submit() {
                Main.default.request(this.$http, this.$snack, 'post', this.url, this.form, function (response) {
                    this.$emit('success', response);
                }.bind(this), {
                    twofaFunc: function (response) {
                        this.$emit('twofaError', response);
                    }.bind(this),
                    errorFunc: function (response) {
                        this.$emit('error', response);
                    }.bind(this),
                });
            },
        }
    }
</script>
