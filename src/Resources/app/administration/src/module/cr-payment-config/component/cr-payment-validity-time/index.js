import template from './cr-payment-validity-time.html.twig';
import './cr-payment-validity-time.scss';

const { Component } = Shopware;

const SECONDS_PER_HOUR = 3600;
const SECONDS_PER_MINUTE = 60;

/**
 * Renders a plain "total seconds" system_config value (e.g. a gateway's transaction
 * validity time) as three separate hours/minutes/seconds number fields instead of a
 * raw integer — used via <component name="cr-payment-validity-time"> in PayNow/PayU's
 * config.xml. The stored value is always the flat second count; decomposing/recomposing
 * happens entirely in this component so the PHP side never has to know about it.
 */
Component.register('cr-payment-validity-time', {
    template,

    inheritAttrs: false,

    props: {
        // Bound by sw-form-field-renderer from the config.xml element.
        value: {
            type: Number,
            required: false,
            default: null,
        },
    },

    computed: {
        totalSeconds() {
            return this.value ?? 0;
        },

        hours: {
            get() {
                return Math.floor(this.totalSeconds / SECONDS_PER_HOUR);
            },
            set(hours) {
                this.emitTotal(this.clamp(hours), this.minutes, this.seconds);
            },
        },

        minutes: {
            get() {
                return Math.floor((this.totalSeconds % SECONDS_PER_HOUR) / SECONDS_PER_MINUTE);
            },
            set(minutes) {
                this.emitTotal(this.hours, this.clamp(minutes, 59), this.seconds);
            },
        },

        seconds: {
            get() {
                return this.totalSeconds % SECONDS_PER_MINUTE;
            },
            set(seconds) {
                this.emitTotal(this.hours, this.minutes, this.clamp(seconds, 59));
            },
        },
    },

    methods: {
        clamp(rawValue, max = null) {
            const n = Math.floor(Number(rawValue));
            const nonNegative = Number.isFinite(n) && n > 0 ? n : 0;

            return max === null ? nonNegative : Math.min(nonNegative, max);
        },

        emitTotal(hours, minutes, seconds) {
            const total = (hours * SECONDS_PER_HOUR) + (minutes * SECONDS_PER_MINUTE) + seconds;
            this.$emit('update:value', total);
        },
    },
});
