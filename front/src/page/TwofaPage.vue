<template>
    <twofa-check v-if="config.hasTwofa"/>
    <twofa-enroll v-else/>
</template>

<script>
    import TwofaCheck from './../component/TwofaCheck';
    import TwofaEnroll from './../component/TwofaEnroll';

    export default {
        components: {TwofaCheck, TwofaEnroll},
        data: () => ({
            config: config,
        }),
    }
</script>
