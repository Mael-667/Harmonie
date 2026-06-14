import { useEffect, useState } from "react";

const DEVICE = {
    mobile: 0,
    tablet: 1,
    pc: 2,
};

function getDevice(){
    const width = document.documentElement.clientWidth;
    if(width < 426) return DEVICE.mobile;
    if(width < 1025) return DEVICE.tablet;
    return DEVICE.pc;
}

export default function useDevice(){
    const [device, setDevice] = useState(getDevice);

    useEffect(() => {
        const handleResize = () => setDevice(getDevice());
        window.addEventListener("resize", handleResize, {passive:true});

        return () => {
            window.removeEventListener("resize", handleResize);
        }
    }, []);

    return [device, DEVICE];
}
