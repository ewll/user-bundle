<template>
    <v-form @submit.prevent="submit">
        <v-text-field prepend-icon="mdi-shield-lock"
                      :error-messages="error"
                      v-model="form.data.contact"
                      :success="success"
                      :success-messages="successMsg"
                      label="Telegram ID"
        >
            <template v-slot:append-outer>
                <v-btn type="submit" :disabled="form.loading" small>{{btnText}}</v-btn>
            </template>
        </v-text-field>
    </v-form>
</template>

<script>
    export default {
        data: () => ({
            config: config,
            btnText: 'Получить код',
            form: null,
            timer: null,
            success: false,
            successMsg: '',
        }),
        created() {
            this.form = Main.default.initForm();
        },
        computed: {
            error() {
                return this.form.errors.contact || this.form.errors.form || null;
            }
        },
        methods: {
            submit() {
                this.success = false;
                this.successMsg = '';
                this.form.data.type = 'telegram';
                Main.default.request(this.$http, this.$snack, 'post', '/2fa/enroll-code', this.form, function () {
                    this.success = true;
                    this.successMsg = 'Отправлено';
                    this.form.loading = true;
                    this.btnText = 59;
                    this.timer = setInterval(this.tick, 1000);
                }.bind(this));
            },
            tick() {
                if (this.btnText === 1) {
                    this.btnText = 'Получить код';
                    this.form.loading = false;
                    clearInterval(this.timer);
                } else {
                    this.btnText--;
                }
            }
        }
    }
</script>
