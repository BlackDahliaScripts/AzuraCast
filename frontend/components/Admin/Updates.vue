<template>
    <h2 class="outside-card-header mb-1">
        {{ $gettext('Update SoundMesh') }}
    </h2>

    <div class="row row-of-cards">
        <div class="col col-md-8">
            <card-page
                header-id="hdr_update_details"
                :title="$gettext('Update Details')"
            >
                <div class="card-body">
                    <div
                        v-if="needsUpdates"
                        class="text-warning"
                    >
                        {{
                            $gettext('Your installation needs to be updated. Updating is recommended for performance and security improvements.')
                        }}
                    </div>
                    <div
                        v-else
                        class="text-success"
                    >
                        {{
                            $gettext('Your installation is up to date! No update is required.')
                        }}
                    </div>
                </div>

                <template #footer_actions>
                    <button
                        type="button"
                        class="btn btn-info"
                        @click="checkForUpdates()"
                    >
                        <icon :icon="IconSync" />
                        {{ $gettext('Check for Updates') }}
                    </button>
                </template>
            </card-page>
        </div>
        <div class="col col-md-4">
            <card-page
                header-id="hdr_release_channel"
                :title="$gettext('Release Channel')"
            >
                <div class="card-body">
                    <p class="card-text">
                        {{ $gettext('Your installation is currently on this release channel:') }}
                    </p>
                    <p class="card-text typography-subheading">
                        {{ langReleaseChannel }}
                    </p>
                </div>

                <template #footer_actions>
                    <a
                        class="btn btn-info"
                        href="/docs/getting-started/updates/release-channels/"
                        target="_blank"
                    >
                        <icon :icon="IconInfo" />
                        {{ $gettext('About Release Channels') }}
                    </a>
                </template>
            </card-page>
        </div>
    </div>
    <div class="row">
        <div class="col col-md-6">
            <card-page
                header-id="hdr_update_via_web"
                :title="$gettext('Update SoundMesh via Web')"
            >
                <template v-if="enableWebUpdates">
                    <div class="card-body">
                        <p class="card-text">
                            {{
                                $gettext('For simple updates where you want to keep your current configuration, you can update directly via your web browser. You will be disconnected from the web interface and listeners will be disconnected from all stations.')
                            }}
                        </p>
                        <p class="card-text">
                            {{
                                $gettext('Backing up your installation is strongly recommended before any update.')
                            }}
                        </p>
                    </div>
                </template>
                <template v-else>
                    <div class="card-body">
                        <p class="card-text">
                            {{
                                $gettext('Web updates are not available for your installation. To update your installation, perform the manual update process instead.')
                            }}
                        </p>
                    </div>
                </template>

                <template
                    v-if="enableWebUpdates"
                    #footer_actions
                >
                    <router-link
                        :to="{ name: 'admin:backups:index' }"
                        class="btn btn-dark"
                    >
                        <icon :icon="IconUpload" />
                        <span>
                            {{ $gettext('Backup') }}
                        </span>
                    </router-link>
                    <button
                        type="button"
                        class="btn btn-success"
                        @click="doUpdate()"
                    >
                        <icon :icon="IconUpdate" />
                        <span>
                            {{ $gettext('Update via Web') }}
                        </span>
                    </button>
                </template>
            </card-page>
        </div>
        <div class="col col-md-6">
            <card-page
                header-id="hdr_manual_updates"
                :title="$gettext('Manual Updates')"
            >
                <div class="card-body">
                    <p class="card-text">
                        {{
                            $gettext('To customize installation settings, or if automatic updates are disabled, you can follow our standard update instructions to update via your SSH console.')
                        }}
                    </p>

                    <a
                        class="btn btn-info"
                        href="/docs/getting-started/updates/"
                        target="_blank"
                    >
                        <icon :icon="IconInfo" />
                        <span>
                            {{ $gettext('Update Instructions') }}
                        </span>
                    </a>
                </div>
            </card-page>
        </div>
    </div>
</template>

<script setup lang="ts">
import {computed, ref} from "vue";
import Icon from "~/components/Common/Icon.vue";
import {useTranslate} from "~/vendor/gettext";
import {useNotify} from "~/functions/useNotify";
import {useAxios} from "~/vendor/axios";
import CardPage from "~/components/Common/CardPage.vue";
import {getApiUrl} from "~/router";
import {IconInfo, IconSync, IconUpdate, IconUpload} from "~/components/Common/icons";
import {useDialog} from "~/functions/useDialog.ts";
import {ApiAdminUpdateDetails} from "~/entities/ApiInterfaces.ts";

const props = withDefaults(
    defineProps<{
        releaseChannel: string,
        initialUpdateInfo?: ApiAdminUpdateDetails,
        enableWebUpdates: boolean,
    }>(),
    {
        initialUpdateInfo: () => ({
            needs_release_update: false,
            needs_rolling_update: false,
        })
    }
);

const updatesApiUrl = getApiUrl('/admin/updates');

const updateInfo = ref<ApiAdminUpdateDetails>(props.initialUpdateInfo);

const {$gettext} = useTranslate();

const langReleaseChannel = computed(() => {
    return (props.releaseChannel === 'stable')
        ? $gettext('Stable')
        : $gettext('Rolling Release');
});

const needsUpdates = computed(() => {
    if (props.releaseChannel === 'stable') {
        return updateInfo.value?.needs_release_update ?? false;
    } else {
        return updateInfo.value?.needs_rolling_update ?? false;
    }
});

const {notifySuccess} = useNotify();
const {axios} = useAxios();

const checkForUpdates = () => {
    void axios.get<ApiAdminUpdateDetails>(updatesApiUrl.value).then(({data}) => {
        updateInfo.value = data;
    });
};

const {showAlert} = useDialog();

const doUpdate = () => {
    void showAlert({
        title: $gettext('Update SoundMesh? Your installation will restart.'),
        confirmButtonText: $gettext('Update via Web')
    }).then((result) => {
        if (result.value) {
            void axios.put(updatesApiUrl.value).then(() => {
                notifySuccess(
                    $gettext('Update started. Your installation will restart shortly.')
                );
            });
        }
    });
};
</script>
