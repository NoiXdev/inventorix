import QrScanner from "qr-scanner";
import { classifyCameraError, type CameraErrorKind } from "./scanner-camera-error";

const scannerContainer = document.querySelector("#scanner-container") as HTMLElement | null;
const scannerVideoElement = document.querySelector("video#scanner-area") as HTMLVideoElement | null;
const scannerStartButton = document.querySelector("#scanner-start-button");
const scannerOutputInput = document.querySelector("#scanner-code") as HTMLInputElement | null;
const scannerErrorElement = document.querySelector("#scanner-error") as HTMLElement | null;

if (!scannerVideoElement) {
    throw new Error("Video Element not found")
}

let scanner = new QrScanner(scannerVideoElement, result => {
    if (scannerOutputInput) {
        scannerOutputInput.value = result;
    }
});

const messageForKind = (kind: CameraErrorKind): string => {
    const dataset = scannerContainer?.dataset;
    if (kind === "permission") {
        return dataset?.errorPermission ?? "";
    }
    if (kind === "not_found") {
        return dataset?.errorNotFound ?? "";
    }
    return dataset?.errorGeneric ?? "";
};

const showError = (message: string) => {
    if (!scannerErrorElement) {
        return;
    }
    scannerErrorElement.textContent = message;
    scannerErrorElement.classList.remove("hidden");
};

const clearError = () => {
    if (!scannerErrorElement) {
        return;
    }
    scannerErrorElement.textContent = "";
    scannerErrorElement.classList.add("hidden");
};

const askForPermission = async () => {
    await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {}
    })
}

scannerStartButton?.addEventListener('click', async () => {
    clearError();

    try {
        await askForPermission();
    } catch (error) {
        showError(messageForKind(classifyCameraError(error)));
        return;
    }

    try {
        await scanner.start();
    } catch (error) {
        showError(messageForKind(classifyCameraError(error)));
    }
})

document.addEventListener("scanner-modal-close", async () => {
    scanner.stop();
    clearError();
})
