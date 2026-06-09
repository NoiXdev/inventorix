import QrScanner from "qr-scanner";

const scannerVideoElement = document.querySelector("video#scanner-area") as HTMLVideoElement | null;
const scannerStartButton = document.querySelector("#scanner-start-button");
const scannerOutputInput = document.querySelector("#scanner-code") as HTMLInputElement | null;

if(!scannerVideoElement) {
    throw new Error("Video Element not found")
}

let scanner = new QrScanner(scannerVideoElement, result => {
    if(scannerOutputInput){
        scannerOutputInput.value = result;
    }
});

scannerStartButton?.addEventListener('click', async () => {
    await askForPermission();
    await scanner.start();

})

document.addEventListener("scanner-modal-close", async () => {
    scanner.stop();
})

const askForPermission = async () => {
    await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {}
    })
}
