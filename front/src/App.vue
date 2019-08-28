<template>
    <v-app id="inspire">
        <v-content>
            <v-container fluid fill-height>
                <v-layout align-center justify-center>
                    <v-flex xs12 sm8 md5>
                        <v-card v-if="config.emailConfirmed">
                            <v-alert :value="true" type="success">
                                Email адрес подтвержден!<br>Теперь вы можете войти.
                            </v-alert>
                        </v-card>
                        <br>
                        <v-alert v-if="successSignup" :value="true" type="success">
                            Вы зарегистрированы!<br>Вам было отправлено письмо для подтверждения адреса.
                        </v-alert>
                        <v-card v-else class="elevation-12">
                            <v-form @submit.prevent="submit">
                                <v-toolbar dark color="primary">
                                    <v-toolbar-title>
                                        <span v-if="config.pageName === 'login'">Вход</span>
                                        <span v-else>Регистрация</span>
                                    </v-toolbar-title>
                                </v-toolbar>
                                <v-card-text class="auth__fieldsContainer">
                                    <v-text-field prepend-icon="mdi-account" name="email" label="Email"
                                                  :error-messages="form.errors.email"
                                                  v-model="form.data.email"></v-text-field>
                                    <v-text-field prepend-icon="mdi-lock" name="password" label="Пароль"
                                                  type="password" :error-messages="form.errors.pass"
                                                  v-model="form.data.pass"></v-text-field>
                                    <div class="error--text formError">
                                        <span v-if="form.errors.form">{{ form.errors.form }}</span>
                                    </div>
                                </v-card-text>
                                <v-card-actions>
                                    <v-btn v-if="config.pageName === 'login'" href="/signup" text>
                                        Регистрация
                                    </v-btn>
                                    <v-btn v-else href="/login" text>Вход</v-btn>
                                    <v-spacer></v-spacer>
                                    <v-btn type="submit" color="primary" :disabled="form.loading">
                                        <span v-if="config.pageName === 'login'">Войти</span>
                                        <span v-else>Зарегистрироваться</span>
                                    </v-btn>
                                </v-card-actions>
                            </v-form>
                        </v-card>
                    </v-flex>
                </v-layout>
            </v-container>
        </v-content>
    </v-app>
</template>

<script>
    export default {
        props: {
            source: String,
        },
        data: () => ({
            config: config,
            form: null,
            successSignup: false,
        }),
        created() {
            this.form = Main.default.initForm({email: '', pass: ''});
        },
        methods: {
            submit() {
                let url = this.config.pageName === 'login' ? '/login' : '/signup';
                Main.default.request(this.$http, this.$snack, 'post', url, this.form, function () {
                    if (this.config.pageName === 'login') {
                        let urlParts = window.location.href.split('#');
                        let sharpParams = urlParts.length === 2 ? '#' + urlParts[1] : '';
                        window.location.href = '/private' + sharpParams
                    } else {
                        this.successSignup = true;
                    }
                }.bind(this));
            },
        }
    }
</script>
