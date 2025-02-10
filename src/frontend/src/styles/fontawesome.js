import Vue from "vue";
import {library} from '@fortawesome/fontawesome-svg-core'
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome'
import {faSquare} from '@fortawesome/free-regular-svg-icons'
import {
    faAngleDoubleDown,
    faAngleDoubleRight,
    faAngleDoubleUp,
    faArrowRight,
    faCalendarWeek,
    faCancel,
    faCaretDown,
    faCaretUp,
    faCheck,
    faCheckSquare,
    faChevronDown,
    faChevronLeft,
    faChevronRight,
    faCircle,
    faCircleHalfStroke,
    faCircleNotch,
    faClock,
    faDownload,
    faEdit,
    faExclamationTriangle,
    faGear,
    faHand,
    faInfoCircle,
    faKey,
    faPlus,
    faPowerOff,
    faPuzzlePiece,
    faQuestionCircle,
    faRefresh,
    faRotateLeft,
    faRotateRight,
    faSave,
    faShieldHalved,
    faShuffle,
    faSignIn,
    faSignOut,
    faTimesCircle,
    faTrash,
    faUnlock,
} from '@fortawesome/free-solid-svg-icons';

library.add(faSquare, faCheckSquare, faGear, faDownload, faSignOut, faSignIn, faShieldHalved, faPuzzlePiece, faKey, faTimesCircle, faCheck,
    faChevronLeft, faChevronRight, faChevronDown, faArrowRight, faQuestionCircle, faPlus, faTrash, faShuffle, faInfoCircle, faCircleNotch,
    faPowerOff, faEdit, faSave, faCancel, faRefresh, faCaretUp, faCaretDown, faAngleDoubleDown, faAngleDoubleRight, faAngleDoubleUp,
    faCalendarWeek, faHand, faClock, faCircleHalfStroke, faUnlock, faCircle, faRotateLeft, faRotateRight, faExclamationTriangle);
Vue.component('fa', FontAwesomeIcon)
