export interface PatientDocument {
    id: string;
    collection: string;
    original_filename: string;
    mime_type: string | null;
    size_bytes: number;
    created_at: string | null;
}
