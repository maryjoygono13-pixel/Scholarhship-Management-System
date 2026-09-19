interface ApplicantData {
  id: number;
  studentId: string;
  firstName?: string;
  lastName?: string;
  name: string;
  email: string;
  phone?: string;
  birthdate?: string;
  address?: string;
  school?: string;
  program: string;
  yearLevel?: string;
  scholarshipType: string;
  gpa?: string | number;
  gwa?: string | number;
  gwaReq?: number;
  failingGrades?: number;
  units?: number;
  enrolled?: boolean;
  docsComplete?: boolean;
  status: string;
  essay?: string;
  remarks?: string;
  createdAt?: string;
}

interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  summary?: any;
  count?: number;
  sent?: number;
  failed?: number;
  total?: number;
}

interface EvaluationApplicant {
  id: string;
  name: string;
  fullName: string;
  studentId: string;
  program: string;
  major: string;
  yearLevel: string;
  semester: string;
  type: string;
  gwa: number;
  gwaReq: number;
  failingGrades: number;
  units: number;
  enrolled: boolean;
  docsComplete: boolean;
  status: string;
  remarks: string;
  grades: Record<string, number>;
}

interface Window {
  apiListApplicants: (status?: string) => Promise<ApplicantData[]>;
  apiGetApplicant: (id: number | string) => Promise<ApplicantData>;
  apiSaveApplicant: (formEl: HTMLFormElement, applicantId?: number | string | null) => Promise<ApiResponse>;
  apiMoveToEvaluation: (id: number | string) => Promise<ApiResponse>;
  apiDecideApplicant: (id: number | string, decision: string) => Promise<ApiResponse>;
  apiListNotifications: (type?: string) => Promise<ApiResponse>;
  apiGetRecipients: (segment: string) => Promise<ApiResponse>;
  apiSendNotification: (payload: Record<string, string>) => Promise<ApiResponse>;
  updateNavCounts: () => Promise<void>;
  editApplicant?: (event: MouseEvent, id: number) => Promise<void>;
  confirmDeleteApplicant?: (event: MouseEvent, id: number) => void;
  confirmDeleteNotif?: (event: MouseEvent, id: number) => void;
  editRecord?: (event: MouseEvent, id: number) => void;
  confirmDeleteRecord?: (event: MouseEvent, id: number) => void;
  openEvalModal?: (id: number) => void;
  editRenewal?: (event: MouseEvent, id: number) => void;
  confirmDeleteRenewal?: (event: MouseEvent, id: number) => void;
  editScholarship?: (event: MouseEvent, id: number) => void;
  confirmDeleteScholarship?: (event: MouseEvent, id: number) => void;
  CURRICULUM_DATA?: Record<string, Record<string, { code: string; name: string }[]>>;
  getCurriculumSubjects?: (program: string, major: string, yearLevel: string) => { code: string; name: string }[];
}

declare const lucide: { createIcons: () => void } | undefined;
declare const Chart: any;
