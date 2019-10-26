<template>
    <div class="text-center">
        <!--<span class="caption">Войти через:</span>-->
        <v-btn v-for="(item,i) in config.oauths" :key="i" tile large color="primary" icon :href="item.url">
            <v-icon>mdi-{{item.name}}</v-icon>
        </v-btn>
    </div>
</template>

<script>
    export default {
        data: () => ({
            config: config,
        }),
    }
</script>
