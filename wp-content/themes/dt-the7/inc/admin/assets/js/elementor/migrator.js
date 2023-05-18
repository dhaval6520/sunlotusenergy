(function ($) {
    "use strict";
    elementor.on("document:loaded", function () {
        var The7ElementorMigrator = function () {
            const sectionMap = ({isInner}) => ({
                ...this.responsive('custom_height_inner', 'min_height'),
                ...this.responsive('content_position', ({deviceValue, breakpoint}) => {
                    const optionsMap = {
                        top: 'flex-start',
                        bottom: 'flex-end',
                        'space-between': 'stretch',
                    };

                    const deviceKey = this.getDeviceKey('flex_align_items', breakpoint);
                    return [[deviceKey, optionsMap[deviceValue] || deviceValue]];
                }),
            });

            const columnMap = () => ({
                ...this.responsive('the7_auto_width', ({deviceValue, breakpoint, settings}) => {
                    var array = [];
                    switch (deviceValue) {
                        case 'maximize':
                            array.push([this.getDeviceKey('_flex_size', breakpoint), 'custom']);
                            array.push([this.getDeviceKey('_flex_grow', breakpoint), 1]);
                            array.push([this.getDeviceKey('_flex_shrink', breakpoint), 1]);
                            break;
                        case 'fit-content':
                            array.push([this.getDeviceKey('the7_size_fit_content', breakpoint),  'yes']);
                            break;
                        case 'minimize':
                            const targetWidthKey = this.getDeviceKey('the7_target_width', breakpoint);
                            let param = 'none';
                            if (settings[targetWidthKey]) {
                                const widthKey = this.getDeviceKey('width', breakpoint);
                                array.push([widthKey, settings[targetWidthKey]]);
                                array.push([this.getDeviceKey('_flex_size', breakpoint), 'none']);
                            }
                            else{
                                array.push([this.getDeviceKey('the7_size_fit_content', breakpoint), 'yes']);
                            }
                            break;
                    }
                    return array;
                }),
                /*...this.responsive('the7_target_width', ({deviceValue, breakpoint}) => {
                    const deviceKey = this.getDeviceKey('width', breakpoint);
                    const newValue = {
                        size: deviceValue,
                        unit: '%',
                    };

                    return [deviceKey, newValue];
                }),*/
                ...this.responsive('content_position', ({deviceValue, breakpoint}) => {
                    const optionsMap = {
                        top: 'flex-start',
                        bottom: 'flex-end',
                        'space-between': 'stretch',
                    };

                    const deviceKey = this.getDeviceKey('flex_align_items', breakpoint);
                    return [[deviceKey, optionsMap[deviceValue] || deviceValue]];
                }),

                ...this.responsive('flex_justify_content', ({deviceValue, breakpoint}) => {
                    const optionsMap = {
                        'space-between': 'stretch',
                    };

                    const deviceKey = this.getDeviceKey('flex_align_items', breakpoint);
                    return [[deviceKey, optionsMap[deviceValue] || deviceValue]];
                }),

                ...this.responsive('align', 'flex_justify_content'),
            });

            const config = {
                section: {
                    legacyControlsMapping: sectionMap,
                },
                column: {
                    legacyControlsMapping: columnMap,
                    normalizeSettings: (settings) => ({
                        ...settings,
                        flex_direction: 'row', // Force it to be column.
                        flex_wrap: 'wrap',
                    }),
                },
            };
            // Private methods
            var methods = {
                /**
                 * Get a mapping object of Legacy-to-Container controls mapping.
                 *
                 * @param {Object} model - Mapping object.
                 *
                 * @return {Object}
                 */
                getControlsMapping: function (model) {
                    const conf = config[model.elType];
                    if (!conf) {
                        return {};
                    }
                    const {legacyControlsMapping: mapping} = conf;
                    return ('function' === typeof mapping) ? mapping(model) : mapping;
                },

                /**
                 * Normalize element settings (adding defaults, etc.) by elType,
                 *
                 * @param {Object} model - Element model.
                 * @param {Object} settings - Settings object after migration.
                 *
                 * @return {Object} - normalized settings.
                 */
                normalizeSettings: function (model, settings) {
                    const conf = config[model.elType];

                    if (!conf.normalizeSettings) {
                        return settings;
                    }

                    return conf.normalizeSettings(settings, model);
                }
            };

            /*
             * Generate a mapping object for responsive controls.
                 *
                 * Usage:
             *  1. responsive( 'old_key', 'new_key' );
             *  2. responsive( 'old_key', ( { key, value, deviceValue, settings, breakpoint } ) => { return [[ key, value ]] } );
             *
             * @param {string} key - Control name without device suffix.
                 * @param {string|function} value - New control name without device suffix, or a callback.
                 *normalizeSettings
                 * @return {array}
                     */
            The7ElementorMigrator.prototype.responsive = function (key, value) {
                const breakpoints = [
                    '', // For desktop.
                    ...Object.keys(elementorFrontend.config.responsive.activeBreakpoints),
                ];

                return Object.fromEntries(breakpoints.map((breakpoint) => {
                    const deviceKey = this.getDeviceKey(key, breakpoint);

                    // Simple responsive rename with string:
                    if ('string' === typeof value) {
                        const newDeviceKey = this.getDeviceKey(value, breakpoint);

                        return [
                            deviceKey,
                            ({settings}) => [[newDeviceKey, settings[deviceKey]]],
                        ];
                    }

                    // Advanced responsive rename with callback:
                    return [deviceKey, ({settings, value: desktopValue}) => value({
                        key,
                        deviceKey,
                        value: desktopValue,
                        deviceValue: settings[deviceKey],
                        settings,
                        breakpoint,
                    })];
                }));
            };

            /**
             * Get a setting key for a device.
             *
             * Examples:
             *  1. getDeviceKey( 'some_control', 'mobile' ) => 'some_control_mobile'.
             *  2. getDeviceKey( 'some_control', '' ) => 'some_control'.
             *
             * @param {string} key - Setting key.
             * @param {string} breakpoint - Breakpoint name.
             *
             * @return {string}
             */
            The7ElementorMigrator.prototype.getDeviceKey = function (key, breakpoint) {
                return [key, breakpoint].filter((v) => !!v).join('_');
            };


            The7ElementorMigrator.prototype.normalizeSettings = function (model, settings) {
                return methods.normalizeSettings(model, settings);
            };

            /**
             * Migrate element settings into new settings object, using a map object.
             *
             * @param {Object} settings - Settings to migrate.
             *
             *  @param {Object} model - Element model.
             *
             * @return {Object}
             */
            The7ElementorMigrator.prototype.migrate = function (settings, model) {
                const map = methods.getControlsMapping(model);
                if (map === undefined) {
                    return settings;
                }
                let copy = [];
                Object.entries({...settings}).forEach(([key, value]) => {
                    const mapped = map[key];
                    // Remove setting.
                    if (null === mapped) {
                        return;
                    }

                    // Simple key conversion:
                    // { old_setting: 'new_setting' }
                    if ('string' === typeof mapped) {
                        copy.push([mapped, value])
                        return;
                    }

                    // Advanced conversion using a callback:
                    // { old_setting: ( { key, value, settings } ) => [ 'new_setting', value ] }
                    if ('function' === typeof mapped) {
                        copy = copy.concat(mapped({key, value, settings}));
                        return;
                    }
                    copy.push([key, value]);
                });

                return Object.fromEntries(copy);
            };

            The7ElementorMigrator.prototype.canConvertToContainer = function (elType) {
                return Object.keys(config).includes(elType);
            };
        };

        //migrate
        var convertType = null;
        $e.commands.on('run:before', function (self, commandName, args) {
            if (commandName === 'container-converter/convert') {
                convertType = null;
                const elType = args.container.type;
                const migrator = new The7ElementorMigrator();
                if (migrator.canConvertToContainer(elType)) {
                    convertType = elType;
                }
            }
            if (convertType !== null && commandName === 'document/elements/create') {
                const migrator = new The7ElementorMigrator();
                if (migrator.canConvertToContainer(convertType)) {
                    let newSettings;
                    const modelOrig = args.model;
                    const modelCopy = Object.assign({}, modelOrig);
                    modelCopy.elType = convertType;
                    newSettings = migrator.migrate(modelCopy.settings, modelCopy);
                    newSettings = migrator.normalizeSettings(modelCopy, newSettings);
                    modelOrig.settings = newSettings;
                }
                convertType = null;
            }

        });
    });
})(jQuery);