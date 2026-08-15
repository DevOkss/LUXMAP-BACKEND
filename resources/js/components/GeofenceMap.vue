<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import L from 'leaflet';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';

const TCGC_CENTER = { lat: 8.065254, lng: 123.756733 };

const props = defineProps<{
    modelValue: { lat: number; lng: number; radius: number };
}>();

const emit = defineEmits<{
    'update:modelValue': [v: { lat: number; lng: number; radius: number }];
}>();

const mapContainer = ref<HTMLElement>();
let map: L.Map | null = null;
let marker: L.Marker | null = null;
let circle: L.Circle | null = null;

function initIcon() {
    delete (L.Icon.Default.prototype as any)._getIconUrl;
    L.Icon.Default.mergeOptions({ iconUrl: markerIcon, iconRetinaUrl: markerIcon2x, shadowUrl: markerShadow });
}

function emitUpdate() {
    if (!marker) return;
    const pos = marker.getLatLng();
    moveCircleToMarker();
    emit('update:modelValue', { lat: pos.lat, lng: pos.lng, radius: props.modelValue.radius });
}

function moveCircleToMarker() {
    if (!marker || !circle) return;
    circle.setLatLng(marker.getLatLng());
}

function updateRadius(r: number) {
    if (!circle) return;
    circle.setRadius(r);
}

watch(() => props.modelValue.radius, (r) => updateRadius(r));

watch([() => props.modelValue.lat, () => props.modelValue.lng], ([lat, lng]) => {
    if (marker && map) {
        marker.setLatLng([lat, lng]);
        circle?.setLatLng([lat, lng]);
        map.setView([lat, lng], map.getZoom());
    }
});

onMounted(() => {
    if (!mapContainer.value) return;
    initIcon();

    const lat = props.modelValue.lat || TCGC_CENTER.lat;
    const lng = props.modelValue.lng || TCGC_CENTER.lng;

    map = L.map(mapContainer.value).setView([lat, lng], 17);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri',
        maxZoom: 20,
    }).addTo(map);

    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    marker.on('drag', moveCircleToMarker);
    marker.on('dragend', emitUpdate);

    circle = L.circle([lat, lng], { radius: props.modelValue.radius, color: '#20673A', fillColor: '#20673A', fillOpacity: 0.15 }).addTo(map);
});

onUnmounted(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <div ref="mapContainer" class="h-64 w-full overflow-hidden rounded-xl border border-gray-300"></div>
</template>
