export type CameraErrorKind = "permission" | "not_found" | "generic";

export function classifyCameraError(error: unknown): CameraErrorKind {
    const name = (error as { name?: string } | null | undefined)?.name ?? "";

    if (name === "NotAllowedError" || name === "SecurityError" || name === "PermissionDeniedError") {
        return "permission";
    }

    if (name === "NotFoundError" || name === "DevicesNotFoundError") {
        return "not_found";
    }

    return "generic";
}
